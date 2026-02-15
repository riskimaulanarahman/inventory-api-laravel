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
            ['tenant_owner'],
            true,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $branchSelection = $this->resolveAssignableBranches(
            $access['context']['tenantId'],
            $validated['branchIds'],
        );
        if ($branchSelection instanceof JsonResponse) {
            return $branchSelection;
        }
        $branchIds = $branchSelection['branchIds'];

        $normalizedEmail = mb_strtolower(trim($validated['email']));
        $existingUser = User::query()->where('email', $normalizedEmail)->first();
        $userId = $existingUser?->id;

        if (!$userId) {
            $created = User::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $validated['displayName'],
                'email' => $normalizedEmail,
                'password' => Hash::make($validated['temporaryPassword']),
            ]);
            $userId = $created->id;
        }

        $existingMembershipElsewhere = DB::table('memberships')
            ->where('profile_id', $userId)
            ->where('tenant_id', '!=', $access['context']['tenantId'])
            ->where('is_active', true)
            ->exists();

        if ($existingMembershipElsewhere) {
            return $this->error('User selain pemilik hanya boleh terdaftar di satu akun bisnis.', 422);
        }

        try {
            $membershipId = DB::transaction(function () use (
                $validated,
                $normalizedEmail,
                $access,
                $userId,
                $branchIds
            ) {
            $now = now();

            DB::table('profiles')->updateOrInsert(
                ['id' => $userId],
                [
                    'email' => $normalizedEmail,
                    'display_name' => $validated['displayName'],
                    'must_reset_password' => true,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            User::query()->where('id', $userId)->update([
                'name' => $validated['displayName'],
                'email' => $normalizedEmail,
                'password' => Hash::make($validated['temporaryPassword']),
            ]);

            $existingMembership = DB::table('memberships')
                ->where('tenant_id', $access['context']['tenantId'])
                ->where('profile_id', $userId)
                ->first(['id', 'role']);

            if ($existingMembership && $existingMembership->role !== 'staff') {
                throw new \RuntimeException('Akun sudah terdaftar dengan peran lain di bisnis ini.');
            }

            $membershipId = $existingMembership?->id ?? (string) Str::uuid();

            DB::table('memberships')->updateOrInsert(
                ['id' => $membershipId],
                [
                    'tenant_id' => $access['context']['tenantId'],
                    'profile_id' => $userId,
                    'role' => 'staff',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('membership_branch_access')
                ->where('membership_id', $membershipId)
                ->delete();

            DB::table('membership_branch_access')->insert(array_map(
                static fn (string $branchId) => [
                    'id' => (string) Str::uuid(),
                    'membership_id' => $membershipId,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                ],
                $branchIds,
            ));

            return $membershipId;
            }, 3);
        } catch (\RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        $staff = $this->findStaffByMembershipId(
            $access['context']['tenantId'],
            $membershipId,
        );

        return $this->ok(['staff' => $staff]);
    }

    public function listStaff(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner'],
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        return $this->ok([
            'staff' => $this->listActiveStaff($access['context']['tenantId']),
        ]);
    }

    public function updateStaff(Request $request, string $membershipId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'displayName' => ['required', 'string', 'min:2'],
            'branchIds' => ['required', 'array', 'min:1'],
            'branchIds.*' => ['required', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner'],
            true,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $target = DB::table('memberships')
            ->where('id', $membershipId)
            ->where('tenant_id', $access['context']['tenantId'])
            ->where('role', 'staff')
            ->where('is_active', true)
            ->first(['id', 'profile_id']);

        if (!$target) {
            return $this->error('Staff tidak ditemukan.', 422);
        }

        $branchSelection = $this->resolveAssignableBranches(
            $access['context']['tenantId'],
            $validated['branchIds'],
        );
        if ($branchSelection instanceof JsonResponse) {
            return $branchSelection;
        }

        $branchIds = $branchSelection['branchIds'];
        $displayName = trim($validated['displayName']);
        if ($displayName === '') {
            return $this->error('Nama staff wajib diisi.', 422);
        }

        DB::transaction(function () use ($target, $displayName, $membershipId, $branchIds) {
            $now = now();

            DB::table('profiles')
                ->where('id', $target->profile_id)
                ->update([
                    'display_name' => $displayName,
                    'updated_at' => $now,
                ]);

            User::query()
                ->where('id', $target->profile_id)
                ->update([
                    'name' => $displayName,
                ]);

            DB::table('memberships')
                ->where('id', $membershipId)
                ->update([
                    'updated_at' => $now,
                ]);

            DB::table('membership_branch_access')
                ->where('membership_id', $membershipId)
                ->delete();

            DB::table('membership_branch_access')->insert(array_map(
                static fn (string $branchId) => [
                    'id' => (string) Str::uuid(),
                    'membership_id' => $membershipId,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                ],
                $branchIds,
            ));
        }, 3);

        $staff = $this->findStaffByMembershipId(
            $access['context']['tenantId'],
            $membershipId,
        );

        return $this->ok(['staff' => $staff]);
    }

    public function resetStaffPassword(Request $request, string $membershipId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'temporaryPassword' => ['required', 'string', 'min:8'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner'],
            true,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $target = DB::table('memberships')
            ->where('id', $membershipId)
            ->where('tenant_id', $access['context']['tenantId'])
            ->where('role', 'staff')
            ->where('is_active', true)
            ->first(['id', 'profile_id']);

        if (!$target) {
            return $this->error('Staff tidak ditemukan.', 422);
        }

        DB::transaction(function () use ($target, $validated) {
            User::query()
                ->where('id', $target->profile_id)
                ->update([
                    'password' => Hash::make($validated['temporaryPassword']),
                ]);

            DB::table('profiles')
                ->where('id', $target->profile_id)
                ->update([
                    'must_reset_password' => true,
                    'updated_at' => now(),
                ]);
        }, 3);

        return $this->ok([
            'ok' => true,
            'message' => 'Kata sandi sementara staff berhasil direset.',
        ]);
    }

    public function deactivateStaff(Request $request, string $membershipId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner'],
            true,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $target = DB::table('memberships')
            ->where('id', $membershipId)
            ->where('tenant_id', $access['context']['tenantId'])
            ->where('role', 'staff')
            ->where('is_active', true)
            ->first(['id']);

        if (!$target) {
            return $this->error('Staff tidak ditemukan.', 422);
        }

        DB::transaction(function () use ($membershipId) {
            DB::table('memberships')
                ->where('id', $membershipId)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            DB::table('membership_branch_access')
                ->where('membership_id', $membershipId)
                ->delete();
        }, 3);

        return $this->ok([
            'ok' => true,
            'message' => 'Staff berhasil dinonaktifkan.',
        ]);
    }

    private function resolveAssignableBranches(string $tenantId, array $branchIds): array|JsonResponse
    {
        $uniqueBranchIds = array_values(array_unique($branchIds));
        if ($uniqueBranchIds === []) {
            return $this->error('Minimal pilih satu outlet.', 422);
        }

        $branches = DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $uniqueBranchIds)
            ->get(['id', 'name', 'code']);

        if ($branches->count() !== count($uniqueBranchIds)) {
            return $this->error('Penugasan outlet/cabang tidak valid.', 422);
        }

        return [
            'branchIds' => $uniqueBranchIds,
            'branches' => $branches->values()->all(),
        ];
    }

    private function listActiveStaff(string $tenantId): array
    {
        $rows = DB::table('memberships')
            ->join('profiles', 'memberships.profile_id', '=', 'profiles.id')
            ->where('memberships.tenant_id', $tenantId)
            ->where('memberships.role', 'staff')
            ->where('memberships.is_active', true)
            ->orderBy('profiles.display_name')
            ->get([
                'memberships.id as membership_id',
                'memberships.profile_id',
                'memberships.role',
                'memberships.is_active',
                'memberships.created_at',
                'memberships.updated_at',
                'profiles.email',
                'profiles.display_name',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $membershipIds = $rows->pluck('membership_id')->all();
        $branchRows = DB::table('membership_branch_access')
            ->join('branches', 'membership_branch_access.branch_id', '=', 'branches.id')
            ->whereIn('membership_branch_access.membership_id', $membershipIds)
            ->where('branches.tenant_id', $tenantId)
            ->orderBy('branches.name')
            ->get([
                'membership_branch_access.membership_id',
                'branches.id',
                'branches.name',
                'branches.code',
            ]);

        $branchesByMembership = $branchRows
            ->groupBy('membership_id')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
            ])->values()->all())
            ->all();

        return $rows->map(function ($row) use ($branchesByMembership) {
            $branches = $branchesByMembership[$row->membership_id] ?? [];

            return [
                'membershipId' => $row->membership_id,
                'profileId' => $row->profile_id,
                'email' => $row->email,
                'displayName' => $row->display_name,
                'role' => $row->role,
                'isActive' => (bool) $row->is_active,
                'branchIds' => array_values(array_map(
                    static fn (array $branch) => $branch['id'],
                    $branches,
                )),
                'branches' => $branches,
                'createdAt' => $row->created_at,
                'updatedAt' => $row->updated_at,
            ];
        })->all();
    }

    private function findStaffByMembershipId(string $tenantId, string $membershipId): ?array
    {
        return collect($this->listActiveStaff($tenantId))
            ->firstWhere('membershipId', $membershipId);
    }
}
