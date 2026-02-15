<?php

namespace App\Http\Controllers\Api;

use App\Services\InventoryDashboardAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryDashboardController extends BaseApiController
{
    public function alerts(
        Request $request,
        string $tenantSlug,
        InventoryDashboardAlertService $service,
    ): JsonResponse {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'location' => ['nullable', 'string', 'regex:/^(all|central|outlet:[0-9a-fA-F-]{36})$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $locationFilter = (string) ($validated['location'] ?? 'all');
        $limit = (int) ($validated['limit'] ?? 5);

        if (str_starts_with($locationFilter, 'outlet:')) {
            $outletId = substr($locationFilter, strlen('outlet:'));
            if (!$this->canAccessBranch($resolved, $outletId)) {
                return $this->error('Outlet tidak ditemukan.', 422);
            }
        }

        $payload = $service->buildLowStockAlerts(
            $resolved['context']['tenantId'],
            $resolved['allowedBranches']->all(),
            $locationFilter,
            $limit,
        );

        return $this->ok($payload);
    }

    private function resolveInventoryContext(Request $request, string $tenantSlug): array|JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $tenantSlug,
            [],
            false,
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $allowedBranches = $this->allowedBranchesForUser($access['context']);

        return [
            'profileId' => $auth['profile']->id,
            'context' => $access['context'],
            'allowedBranches' => $allowedBranches,
            'allowedBranchIds' => $allowedBranches->pluck('id')->all(),
        ];
    }

    private function allowedBranchesForUser(array $context)
    {
        $query = DB::table('branches')
            ->where('tenant_id', $context['tenantId'])
            ->where('code', '!=', 'PST')
            ->orderBy('name');

        if ($context['membershipRole'] === 'staff') {
            $branchIds = $context['accessibleBranchIds'] ?? [];

            if ($branchIds === []) {
                return collect();
            }

            $query->whereIn('id', $branchIds);
        }

        return $query->get([
            'id',
            'name',
            'code',
        ]);
    }

    private function canAccessBranch(array $resolved, string $branchId): bool
    {
        if ($resolved['context']['membershipRole'] !== 'staff') {
            return DB::table('branches')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $branchId)
                ->where('code', '!=', 'PST')
                ->exists();
        }

        $allowed = array_flip($resolved['allowedBranchIds']);

        return isset($allowed[$branchId]);
    }
}
