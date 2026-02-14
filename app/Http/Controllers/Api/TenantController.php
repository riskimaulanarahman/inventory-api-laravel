<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantController extends BaseApiController
{
    public function context(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $tenantSlug = $request->query('tenantSlug');

        $memberships = DB::table('memberships')
            ->join('tenants', 'memberships.tenant_id', '=', 'tenants.id')
            ->where('memberships.profile_id', $auth['profile']->id)
            ->where('memberships.is_active', true)
            ->where('tenants.status', 'active')
            ->orderBy('memberships.created_at')
            ->get([
                'memberships.tenant_id',
                'memberships.role',
                'tenants.slug as tenant_slug',
                'tenants.name as tenant_name',
            ]);

        if (!$tenantSlug) {
            return $this->ok([
                'profile' => [
                    'id' => $auth['profile']->id,
                    'email' => $auth['profile']->email,
                    'displayName' => $auth['profile']->display_name,
                ],
                'memberships' => $memberships->map(fn ($item) => [
                    'tenantId' => $item->tenant_id,
                    'tenantSlug' => $item->tenant_slug,
                    'tenantName' => $item->tenant_name,
                    'role' => $item->role,
                ])->all(),
            ]);
        }

        $access = $this->loadTenantAccess($auth['profile']->id, (string) $tenantSlug);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        return $this->ok([
            'context' => $access['context'],
        ]);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'email' => ['required', 'email'],
            'displayName' => ['required', 'string', 'min:2'],
            'role' => ['required', 'in:tenant_admin,staff'],
            'temporaryPassword' => ['required', 'string', 'min:8'],
            'branchIds' => ['required', 'array', 'min:1'],
            'branchIds.*' => ['required', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner', 'tenant_admin'],
            true,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $branchCount = DB::table('branches')
            ->where('tenant_id', $access['context']['tenantId'])
            ->whereIn('id', $validated['branchIds'])
            ->count();

        if ($branchCount !== count($validated['branchIds'])) {
            return $this->error('Branch assignment tidak valid.', 422);
        }

        $existingUser = User::query()->where('email', $validated['email'])->first();
        $userId = $existingUser?->id;

        if (!$userId) {
            $created = User::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $validated['displayName'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['temporaryPassword']),
            ]);
            $userId = $created->id;
        }

        $existingMembershipElsewhere = DB::table('memberships')
            ->where('profile_id', $userId)
            ->where('tenant_id', '!=', $access['context']['tenantId'])
            ->exists();

        if ($existingMembershipElsewhere) {
            return $this->error('User non-owner hanya boleh tergabung di satu tenant.', 422);
        }

        $membership = DB::transaction(function () use ($validated, $access, $userId) {
            $now = now();

            DB::table('profiles')->updateOrInsert(
                ['id' => $userId],
                [
                    'email' => $validated['email'],
                    'display_name' => $validated['displayName'],
                    'must_reset_password' => true,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $membershipId = (string) Str::uuid();
            DB::table('memberships')->insert([
                'id' => $membershipId,
                'tenant_id' => $access['context']['tenantId'],
                'profile_id' => $userId,
                'role' => $validated['role'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('membership_branch_access')->insert(array_map(
                static fn (string $branchId) => [
                    'id' => (string) Str::uuid(),
                    'membership_id' => $membershipId,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                ],
                $validated['branchIds'],
            ));

            return DB::table('memberships')->where('id', $membershipId)->first();
        }, 3);

        return $this->ok(['membership' => $membership]);
    }
}
