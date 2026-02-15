<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InventoryDashboardAlertService
{
    /**
     * @param array<int, object> $allowedBranches
     */
    public function buildLowStockAlerts(
        string $tenantId,
        array $allowedBranches,
        string $locationFilter = 'all',
        int $limit = 5,
    ): array {
        $safeLimit = max(1, min(50, $limit));
        $allowedBranchIds = array_values(array_map(
            static fn (object $branch) => $branch->id,
            $allowedBranches,
        ));

        $outletById = [];
        $activeProductIdsByOutlet = [];
        foreach ($allowedBranches as $branch) {
            $outletById[$branch->id] = $branch;
            $activeProductIdsByOutlet[$branch->id] = [];
        }

        $products = DB::table('inventory_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'central_stock',
                'minimum_low_stock',
            ]);

        $productById = [];
        foreach ($products as $product) {
            $productById[$product->id] = $product;
        }

        $outletStockMap = [];
        if ($allowedBranchIds !== []) {
            $outletStocks = DB::table('inventory_branch_stocks')
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', $allowedBranchIds)
                ->get(['branch_id', 'product_id', 'qty']);

            foreach ($outletStocks as $stock) {
                $outletStockMap[$stock->branch_id.':'.$stock->product_id] = (int) $stock->qty;
                $activeProductIdsByOutlet[$stock->branch_id][$stock->product_id] = true;
            }

            $outletMovements = DB::table('inventory_movements')
                ->where('tenant_id', $tenantId)
                ->where('location_kind', 'outlet')
                ->whereIn('branch_id', $allowedBranchIds)
                ->get(['branch_id', 'product_id']);

            foreach ($outletMovements as $movement) {
                if (!isset($activeProductIdsByOutlet[$movement->branch_id])) {
                    continue;
                }
                $activeProductIdsByOutlet[$movement->branch_id][$movement->product_id] = true;
            }

            $transferSources = DB::table('inventory_transfers')
                ->where('tenant_id', $tenantId)
                ->where('source_kind', 'outlet')
                ->whereNotNull('source_branch_id')
                ->whereIn('source_branch_id', $allowedBranchIds)
                ->get(['source_branch_id', 'product_id']);

            foreach ($transferSources as $source) {
                if (!isset($activeProductIdsByOutlet[$source->source_branch_id])) {
                    continue;
                }
                $activeProductIdsByOutlet[$source->source_branch_id][$source->product_id] = true;
            }

            $transferDestinations = DB::table('inventory_transfer_dests')
                ->join(
                    'inventory_transfers',
                    'inventory_transfer_dests.transfer_id',
                    '=',
                    'inventory_transfers.id',
                )
                ->where('inventory_transfers.tenant_id', $tenantId)
                ->whereIn('inventory_transfer_dests.branch_id', $allowedBranchIds)
                ->get([
                    'inventory_transfer_dests.branch_id',
                    'inventory_transfers.product_id',
                ]);

            foreach ($transferDestinations as $destination) {
                if (!isset($activeProductIdsByOutlet[$destination->branch_id])) {
                    continue;
                }
                $activeProductIdsByOutlet[$destination->branch_id][$destination->product_id] = true;
            }
        }

        $candidates = [];

        $addCentralCandidates = function () use ($products, &$candidates): void {
            foreach ($products as $product) {
                $currentStock = (int) $product->central_stock;
                $minimumLowStock = (int) $product->minimum_low_stock;
                if ($currentStock > $minimumLowStock) {
                    continue;
                }

                $candidates[] = [
                    'productId' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'currentStock' => $currentStock,
                    'minimumLowStock' => $minimumLowStock,
                    'gap' => max(0, $minimumLowStock - $currentStock),
                    'locationKind' => 'central',
                    'locationKey' => 'central',
                    'locationLabel' => 'Pusat',
                ];
            }
        };

        $addOutletCandidates = function (string $outletId) use (
            &$candidates,
            $activeProductIdsByOutlet,
            $outletStockMap,
            $productById,
            $outletById,
        ): void {
            if (!isset($activeProductIdsByOutlet[$outletId])) {
                return;
            }

            $outlet = $outletById[$outletId] ?? null;
            if (!$outlet) {
                return;
            }

            foreach (array_keys($activeProductIdsByOutlet[$outletId]) as $productId) {
                $product = $productById[$productId] ?? null;
                if (!$product) {
                    continue;
                }

                $currentStock = (int) ($outletStockMap[$outletId.':'.$productId] ?? 0);
                $minimumLowStock = (int) $product->minimum_low_stock;
                if ($currentStock > $minimumLowStock) {
                    continue;
                }

                $candidates[] = [
                    'productId' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'currentStock' => $currentStock,
                    'minimumLowStock' => $minimumLowStock,
                    'gap' => max(0, $minimumLowStock - $currentStock),
                    'locationKind' => 'outlet',
                    'locationKey' => 'outlet:'.$outletId,
                    'locationLabel' => $outlet->name.' ('.$outlet->code.')',
                    'outletId' => $outletId,
                ];
            }
        };

        if ($locationFilter === 'central') {
            $addCentralCandidates();
        } elseif (str_starts_with($locationFilter, 'outlet:')) {
            $addOutletCandidates(substr($locationFilter, strlen('outlet:')));
        } else {
            $addCentralCandidates();
            foreach ($allowedBranchIds as $branchId) {
                $addOutletCandidates($branchId);
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['gap'] !== $b['gap']) {
                return $b['gap'] <=> $a['gap'];
            }

            if ($a['currentStock'] !== $b['currentStock']) {
                return $a['currentStock'] <=> $b['currentStock'];
            }

            $locationCompare = strcmp($a['locationLabel'], $b['locationLabel']);
            if ($locationCompare !== 0) {
                return $locationCompare;
            }

            return strcmp($a['name'], $b['name']);
        });

        return [
            'locationFilter' => $locationFilter,
            'lowStockCount' => count($candidates),
            'lowStockPriorities' => array_slice($candidates, 0, $safeLimit),
            'asOf' => now()->toISOString(),
        ];
    }
}
