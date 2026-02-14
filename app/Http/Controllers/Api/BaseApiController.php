<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

abstract class BaseApiController extends Controller
{
    protected function ok(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    protected function error(string|array $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    protected function validateInput(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->toArray(), 422);
        }

        return $validator->validated();
    }

    protected function authContext(Request $request): array|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $profile = DB::table('profiles')->where('id', $user->id)->first();

        if (!$profile || !$profile->is_active) {
            return $this->error('Forbidden', 403);
        }

        return [
            'user' => $user,
            'profile' => $profile,
        ];
    }

    protected function requirePlatformAdmin(string $profileId): ?JsonResponse
    {
        $exists = DB::table('platform_admins')->where('profile_id', $profileId)->exists();

        if (!$exists) {
            return $this->error('Platform admin only', 403);
        }

        return null;
    }

    protected function loadTenantAccess(
        string $profileId,
        string $tenantSlug,
        array $allowedRoles = [],
        bool $requireWritable = false,
    ): array|JsonResponse {
        $membership = DB::table('memberships')
            ->join('tenants', 'memberships.tenant_id', '=', 'tenants.id')
            ->where('memberships.profile_id', $profileId)
            ->where('memberships.is_active', true)
            ->where('tenants.slug', $tenantSlug)
            ->where('tenants.status', 'active')
            ->select([
                'memberships.id as membership_id',
                'memberships.tenant_id',
                'memberships.role',
                'tenants.slug as tenant_slug',
                'tenants.name as tenant_name',
            ])
            ->first();

        if (!$membership) {
            return $this->error('Tenant access denied', 403);
        }

        if ($allowedRoles !== [] && !in_array($membership->role, $allowedRoles, true)) {
            return $this->error('Insufficient role', 403);
        }

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $membership->tenant_id)
            ->orderByDesc('updated_at')
            ->first();

        $isReadOnly = !$subscription || !in_array($subscription->status, ['trialing', 'active'], true);

        if ($requireWritable && $isReadOnly) {
            return response()->json([
                'error' => 'BILLING_READ_ONLY',
                'message' => 'Langganan tidak aktif. Akses hanya baca sampai pembayaran tervalidasi.',
            ], 403);
        }

        $branchIds = DB::table('membership_branch_access')
            ->where('membership_id', $membership->membership_id)
            ->pluck('branch_id')
            ->values()
            ->all();

        return [
            'context' => [
                'tenantId' => $membership->tenant_id,
                'tenantSlug' => $membership->tenant_slug,
                'tenantName' => $membership->tenant_name,
                'membershipId' => $membership->membership_id,
                'membershipRole' => $membership->role,
                'accessibleBranchIds' => $branchIds,
                'subscriptionStatus' => $subscription?->status,
                'isReadOnly' => $isReadOnly,
            ],
        ];
    }
}
