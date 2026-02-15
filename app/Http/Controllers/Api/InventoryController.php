<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends BaseApiController
{
    public function snapshot(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return $this->ok($this->snapshotPayload($resolved['context'], $resolved['allowedBranches']));
    }

    public function createCategory(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        if ($name === '') {
            return $this->error('Nama kategori wajib diisi.', 422);
        }

        $exists = DB::table('inventory_categories')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return $this->error('Kategori sudah ada. Gunakan nama lain.', 422);
        }

        DB::table('inventory_categories')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $resolved['context']['tenantId'],
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Kategori berhasil ditambahkan.',
        ]);
    }

    public function updateCategory(Request $request, string $tenantSlug, string $categoryId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        if ($name === '') {
            return $this->error('Nama kategori wajib diisi.', 422);
        }

        $target = DB::table('inventory_categories')
            ->where('id', $categoryId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Kategori tidak ditemukan.', 422);
        }

        $duplicate = DB::table('inventory_categories')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', '!=', $categoryId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return $this->error('Kategori sudah ada. Gunakan nama lain.', 422);
        }

        DB::table('inventory_categories')
            ->where('id', $categoryId)
            ->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Kategori berhasil diperbarui.',
        ]);
    }

    public function deleteCategory(Request $request, string $tenantSlug, string $categoryId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $target = DB::table('inventory_categories')
            ->where('id', $categoryId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Kategori tidak ditemukan.', 422);
        }

        $used = DB::table('inventory_products')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('category_id', $categoryId)
            ->exists();

        if ($used) {
            return $this->error('Kategori tidak bisa dihapus karena masih dipakai produk.', 422);
        }

        DB::table('inventory_categories')->where('id', $categoryId)->delete();

        return $this->ok([
            'ok' => true,
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    public function createUnit(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        if ($name === '') {
            return $this->error('Nama satuan wajib diisi.', 422);
        }

        $exists = DB::table('inventory_units')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return $this->error('Satuan sudah ada. Gunakan nama lain.', 422);
        }

        DB::table('inventory_units')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $resolved['context']['tenantId'],
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Satuan berhasil ditambahkan.',
        ]);
    }

    public function updateUnit(Request $request, string $tenantSlug, string $unitId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        if ($name === '') {
            return $this->error('Nama satuan wajib diisi.', 422);
        }

        $target = DB::table('inventory_units')
            ->where('id', $unitId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Satuan tidak ditemukan.', 422);
        }

        $duplicate = DB::table('inventory_units')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', '!=', $unitId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return $this->error('Satuan sudah ada. Gunakan nama lain.', 422);
        }

        DB::table('inventory_units')
            ->where('id', $unitId)
            ->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Satuan berhasil diperbarui.',
        ]);
    }

    public function deleteUnit(Request $request, string $tenantSlug, string $unitId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $target = DB::table('inventory_units')
            ->where('id', $unitId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Satuan tidak ditemukan.', 422);
        }

        $used = DB::table('inventory_products')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('unit_id', $unitId)
            ->exists();

        if ($used) {
            return $this->error('Satuan tidak bisa dihapus karena masih dipakai produk.', 422);
        }

        DB::table('inventory_units')->where('id', $unitId)->delete();

        return $this->ok([
            'ok' => true,
            'message' => 'Satuan berhasil dihapus.',
        ]);
    }

    public function createProduct(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
            'sku' => ['required', 'string', 'min:1'],
            'initialStock' => ['required', 'integer', 'min:0'],
            'minimumLowStock' => ['required', 'integer', 'min:0'],
            'categoryId' => ['required', 'uuid'],
            'unitId' => ['required', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        $sku = strtoupper(trim($validated['sku']));

        if ($name === '' || $sku === '') {
            return $this->error('Nama produk dan SKU wajib diisi.', 422);
        }

        $categoryExists = DB::table('inventory_categories')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', $validated['categoryId'])
            ->exists();

        if (!$categoryExists) {
            return $this->error('Kategori wajib dipilih.', 422);
        }

        $unitExists = DB::table('inventory_units')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', $validated['unitId'])
            ->exists();

        if (!$unitExists) {
            return $this->error('Satuan wajib dipilih.', 422);
        }

        $skuExists = DB::table('inventory_products')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->whereRaw('UPPER(sku) = ?', [$sku])
            ->exists();

        if ($skuExists) {
            return $this->error('SKU sudah terpakai. Gunakan SKU lain.', 422);
        }

        DB::transaction(function () use ($resolved, $validated, $name, $sku) {
            $productId = (string) Str::uuid();

            DB::table('inventory_products')->insert([
                'id' => $productId,
                'tenant_id' => $resolved['context']['tenantId'],
                'category_id' => $validated['categoryId'],
                'unit_id' => $validated['unitId'],
                'name' => $name,
                'sku' => $sku,
                'central_stock' => $validated['initialStock'],
                'minimum_low_stock' => $validated['minimumLowStock'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ((int) $validated['initialStock'] > 0) {
                $this->insertMovementLog([
                    'tenantId' => $resolved['context']['tenantId'],
                    'productId' => $productId,
                    'productName' => $name,
                    'branchId' => null,
                    'type' => 'in',
                    'qty' => (int) $validated['initialStock'],
                    'note' => 'Stok awal produk',
                    'delta' => (int) $validated['initialStock'],
                    'balanceAfter' => (int) $validated['initialStock'],
                    'locationKind' => 'central',
                    'locationId' => 'central',
                    'locationLabel' => 'Pusat',
                    'countedStock' => null,
                    'createdBy' => $resolved['profileId'],
                ]);
            }
        }, 3);

        return $this->ok([
            'ok' => true,
            'message' => 'Produk berhasil ditambahkan.',
        ]);
    }

    public function updateProduct(Request $request, string $tenantSlug, string $productId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
            'sku' => ['required', 'string', 'min:1'],
            'minimumLowStock' => ['required', 'integer', 'min:0'],
            'categoryId' => ['required', 'uuid'],
            'unitId' => ['required', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $target = DB::table('inventory_products')
            ->where('id', $productId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Produk tidak ditemukan.', 422);
        }

        $name = trim($validated['name']);
        $sku = strtoupper(trim($validated['sku']));

        if ($name === '' || $sku === '') {
            return $this->error('Nama produk dan SKU wajib diisi.', 422);
        }

        $categoryExists = DB::table('inventory_categories')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', $validated['categoryId'])
            ->exists();

        if (!$categoryExists) {
            return $this->error('Kategori wajib dipilih.', 422);
        }

        $unitExists = DB::table('inventory_units')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', $validated['unitId'])
            ->exists();

        if (!$unitExists) {
            return $this->error('Satuan wajib dipilih.', 422);
        }

        $duplicateSku = DB::table('inventory_products')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', '!=', $productId)
            ->whereRaw('UPPER(sku) = ?', [$sku])
            ->exists();

        if ($duplicateSku) {
            return $this->error('SKU sudah terpakai. Gunakan SKU lain.', 422);
        }

        DB::table('inventory_products')
            ->where('id', $productId)
            ->update([
                'name' => $name,
                'sku' => $sku,
                'minimum_low_stock' => (int) $validated['minimumLowStock'],
                'category_id' => $validated['categoryId'],
                'unit_id' => $validated['unitId'],
                'updated_at' => now(),
            ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Produk berhasil diperbarui.',
        ]);
    }

    public function deleteProduct(Request $request, string $tenantSlug, string $productId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $target = DB::table('inventory_products')
            ->where('id', $productId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->first();

        if (!$target) {
            return $this->error('Produk tidak ditemukan.', 422);
        }

        DB::table('inventory_products')->where('id', $productId)->delete();

        return $this->ok([
            'ok' => true,
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    public function createOutlet(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
            'code' => ['required', 'string', 'min:1'],
            'address' => ['required', 'string', 'min:1'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $name = trim($validated['name']);
        $code = strtoupper(trim($validated['code']));
        $address = trim($validated['address']);

        if ($name === '' || $code === '' || $address === '') {
            return $this->error('Nama outlet, kode outlet, dan alamat wajib diisi.', 422);
        }

        if ($code === 'PST') {
            return $this->error('Kode outlet PST adalah kode sistem dan tidak dapat digunakan.', 422);
        }

        $duplicate = DB::table('branches')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->whereRaw('UPPER(code) = ?', [$code])
            ->exists();

        if ($duplicate) {
            return $this->error('Kode outlet sudah terpakai.', 422);
        }

        DB::transaction(function () use ($resolved, $name, $code, $address, $validated) {
            $branchId = (string) Str::uuid();

            DB::table('branches')->insert([
                'id' => $branchId,
                'tenant_id' => $resolved['context']['tenantId'],
                'name' => $name,
                'code' => $code,
                'address' => $address,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($resolved['context']['membershipRole'] === 'staff') {
                DB::table('membership_branch_access')->updateOrInsert(
                    [
                        'membership_id' => $resolved['context']['membershipId'],
                        'branch_id' => $branchId,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'created_at' => now(),
                    ],
                );
            }
        }, 3);

        return $this->ok([
            'ok' => true,
            'message' => 'Outlet berhasil ditambahkan.',
        ]);
    }

    public function updateOutlet(Request $request, string $tenantSlug, string $outletId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        if (!$this->canAccessBranch($resolved, $outletId)) {
            return $this->error('Outlet tidak ditemukan.', 422);
        }

        $validated = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:1'],
            'code' => ['required', 'string', 'min:1'],
            'address' => ['required', 'string', 'min:1'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $target = DB::table('branches')
            ->where('id', $outletId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('code', '!=', 'PST')
            ->first();

        if (!$target) {
            return $this->error('Outlet tidak ditemukan.', 422);
        }

        $name = trim($validated['name']);
        $code = strtoupper(trim($validated['code']));
        $address = trim($validated['address']);

        if ($name === '' || $code === '' || $address === '') {
            return $this->error('Nama outlet, kode outlet, dan alamat wajib diisi.', 422);
        }

        if ($code === 'PST') {
            return $this->error('Kode outlet PST adalah kode sistem dan tidak dapat digunakan.', 422);
        }

        $duplicate = DB::table('branches')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('id', '!=', $outletId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->exists();

        if ($duplicate) {
            return $this->error('Kode outlet sudah terpakai.', 422);
        }

        DB::table('branches')
            ->where('id', $outletId)
            ->update([
                'name' => $name,
                'code' => $code,
                'address' => $address,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'updated_at' => now(),
            ]);

        return $this->ok([
            'ok' => true,
            'message' => 'Outlet berhasil diperbarui.',
        ]);
    }

    public function deleteOutlet(Request $request, string $tenantSlug, string $outletId): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        if (!$this->canAccessBranch($resolved, $outletId)) {
            return $this->error('Outlet tidak ditemukan.', 422);
        }

        $target = DB::table('branches')
            ->where('id', $outletId)
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('code', '!=', 'PST')
            ->first();

        if (!$target) {
            return $this->error('Outlet tidak ditemukan.', 422);
        }

        $usedInMovement = DB::table('inventory_movements')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('branch_id', $outletId)
            ->exists();

        if ($usedInMovement) {
            return $this->error('Outlet tidak bisa dihapus karena sudah dipakai pada riwayat pergerakan.', 422);
        }

        $usedInTransfer = DB::table('inventory_transfers')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('source_branch_id', $outletId)
            ->exists() || DB::table('inventory_transfer_dests')
            ->where('branch_id', $outletId)
            ->exists();

        if ($usedInTransfer) {
            return $this->error('Outlet tidak bisa dihapus karena sudah dipakai pada riwayat transfer.', 422);
        }

        $hasStock = DB::table('inventory_branch_stocks')
            ->where('tenant_id', $resolved['context']['tenantId'])
            ->where('branch_id', $outletId)
            ->where('qty', '>', 0)
            ->exists();

        if ($hasStock) {
            return $this->error('Outlet tidak bisa dihapus karena masih memiliki stok produk.', 422);
        }

        DB::table('branches')->where('id', $outletId)->delete();

        return $this->ok([
            'ok' => true,
            'message' => 'Outlet berhasil dihapus.',
        ]);
    }

    public function createMovement(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'productId' => ['required', 'uuid'],
            'qty' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:in,out'],
            'note' => ['nullable', 'string'],
            'location' => ['required', 'array'],
            'location.kind' => ['required', 'in:central,outlet'],
            'location.outletId' => ['nullable', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $note = trim((string) ($validated['note'] ?? ''));
        $qty = (int) $validated['qty'];

        try {
            DB::transaction(function () use ($resolved, $validated, $qty, $note) {
            $product = DB::table('inventory_products')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $validated['productId'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new \RuntimeException('Produk tidak ditemukan.');
            }

            $location = $validated['location'];
            $type = $validated['type'];

            if ($location['kind'] === 'central') {
                $currentStock = (int) $product->central_stock;
                if ($type === 'out' && $qty > $currentStock) {
                    throw new \RuntimeException('Stok keluar gagal. Jumlah melebihi stok tersedia.');
                }

                $delta = $type === 'in' ? $qty : -$qty;
                $nextStock = $currentStock + $delta;

                DB::table('inventory_products')
                    ->where('id', $product->id)
                    ->update([
                        'central_stock' => $nextStock,
                        'updated_at' => now(),
                    ]);

                $this->insertMovementLog([
                    'tenantId' => $resolved['context']['tenantId'],
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'branchId' => null,
                    'type' => $type,
                    'qty' => $qty,
                    'note' => $note !== '' ? $note : ($type === 'in' ? 'Stok masuk' : 'Stok keluar'),
                    'delta' => $delta,
                    'balanceAfter' => $nextStock,
                    'locationKind' => 'central',
                    'locationId' => 'central',
                    'locationLabel' => 'Pusat',
                    'countedStock' => null,
                    'createdBy' => $resolved['profileId'],
                ]);

                return;
            }

            $outletId = $location['outletId'] ?? null;
            if (!$outletId) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            if (!$this->canAccessBranch($resolved, $outletId)) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            $branch = DB::table('branches')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $outletId)
                ->first();

            if (!$branch) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            $stock = $this->loadBranchStockForUpdate($resolved['context']['tenantId'], $outletId, $product->id);
            $currentStock = (int) $stock->qty;

            if ($type === 'out' && $qty > $currentStock) {
                throw new \RuntimeException('Stok keluar gagal. Jumlah melebihi stok tersedia.');
            }

            $delta = $type === 'in' ? $qty : -$qty;
            $nextStock = $currentStock + $delta;

            DB::table('inventory_branch_stocks')
                ->where('id', $stock->id)
                ->update([
                    'qty' => $nextStock,
                    'updated_at' => now(),
                ]);

            $this->insertMovementLog([
                'tenantId' => $resolved['context']['tenantId'],
                'productId' => $product->id,
                'productName' => $product->name,
                'branchId' => $outletId,
                'type' => $type,
                'qty' => $qty,
                'note' => $note !== '' ? $note : ($type === 'in' ? 'Stok masuk' : 'Stok keluar'),
                'delta' => $delta,
                'balanceAfter' => $nextStock,
                'locationKind' => 'outlet',
                'locationId' => $outletId,
                'locationLabel' => $branch->name,
                'countedStock' => null,
                'createdBy' => $resolved['profileId'],
            ]);
            }, 3);
        } catch (\RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->ok([
            'ok' => true,
            'message' => $validated['type'] === 'in'
                ? 'Transaksi stok masuk berhasil disimpan.'
                : 'Transaksi stok keluar berhasil disimpan.',
        ]);
    }

    public function createOpname(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'productId' => ['required', 'uuid'],
            'actualStock' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'location' => ['required', 'array'],
            'location.kind' => ['required', 'in:central,outlet'],
            'location.outletId' => ['nullable', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $actualStock = (int) $validated['actualStock'];
        $note = trim((string) ($validated['note'] ?? ''));

        try {
            $delta = DB::transaction(function () use ($resolved, $validated, $actualStock, $note) {
            $product = DB::table('inventory_products')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $validated['productId'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new \RuntimeException('Produk tidak ditemukan untuk opname.');
            }

            $location = $validated['location'];

            if ($location['kind'] === 'central') {
                $currentStock = (int) $product->central_stock;
                $deltaValue = $actualStock - $currentStock;

                DB::table('inventory_products')
                    ->where('id', $product->id)
                    ->update([
                        'central_stock' => $actualStock,
                        'updated_at' => now(),
                    ]);

                $this->insertMovementLog([
                    'tenantId' => $resolved['context']['tenantId'],
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'branchId' => null,
                    'type' => 'opname',
                    'qty' => abs($deltaValue),
                    'note' => $note !== '' ? $note : 'Penyesuaian stok opname',
                    'delta' => $deltaValue,
                    'balanceAfter' => $actualStock,
                    'locationKind' => 'central',
                    'locationId' => 'central',
                    'locationLabel' => 'Pusat',
                    'countedStock' => $actualStock,
                    'createdBy' => $resolved['profileId'],
                ]);

                return $deltaValue;
            }

            $outletId = $location['outletId'] ?? null;
            if (!$outletId) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            if (!$this->canAccessBranch($resolved, $outletId)) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            $branch = DB::table('branches')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $outletId)
                ->first();

            if (!$branch) {
                throw new \RuntimeException('Cabang/Outlet harus dipilih.');
            }

            $stock = $this->loadBranchStockForUpdate($resolved['context']['tenantId'], $outletId, $product->id);
            $currentStock = (int) $stock->qty;
            $deltaValue = $actualStock - $currentStock;

            DB::table('inventory_branch_stocks')
                ->where('id', $stock->id)
                ->update([
                    'qty' => $actualStock,
                    'updated_at' => now(),
                ]);

            $this->insertMovementLog([
                'tenantId' => $resolved['context']['tenantId'],
                'productId' => $product->id,
                'productName' => $product->name,
                'branchId' => $outletId,
                'type' => 'opname',
                'qty' => abs($deltaValue),
                'note' => $note !== '' ? $note : 'Penyesuaian stok opname',
                'delta' => $deltaValue,
                'balanceAfter' => $actualStock,
                'locationKind' => 'outlet',
                'locationId' => $outletId,
                'locationLabel' => $branch->name,
                'countedStock' => $actualStock,
                'createdBy' => $resolved['profileId'],
            ]);

            return $deltaValue;
            }, 3);
        } catch (\RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->ok([
            'ok' => true,
            'message' => $delta === 0
                ? 'Opname tersimpan. Tidak ada perubahan stok.'
                : 'Opname tersimpan dengan penyesuaian stok.',
        ]);
    }

    public function createTransfer(Request $request, string $tenantSlug): JsonResponse
    {
        $resolved = $this->resolveInventoryContext($request, $tenantSlug, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $validated = $this->validateInput($request, [
            'productId' => ['required', 'uuid'],
            'source' => ['required', 'array'],
            'source.kind' => ['required', 'in:central,outlet'],
            'source.outletId' => ['nullable', 'uuid'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.outletId' => ['required', 'uuid'],
            'destinations.*.qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $note = trim((string) ($validated['note'] ?? ''));

        try {
            DB::transaction(function () use ($resolved, $validated, $note) {
            $product = DB::table('inventory_products')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $validated['productId'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new \RuntimeException('Produk transfer tidak ditemukan.');
            }

            $source = $validated['source'];
            $sourceKind = $source['kind'];
            $sourceOutletId = $source['outletId'] ?? null;

            if ($sourceKind === 'outlet') {
                if (!$sourceOutletId || !$this->canAccessBranch($resolved, $sourceOutletId)) {
                    throw new \RuntimeException('Outlet sumber harus dipilih.');
                }
            }

            $destinations = collect($validated['destinations'])
                ->map(fn ($row) => [
                    'outletId' => $row['outletId'],
                    'qty' => (int) $row['qty'],
                ])
                ->values()
                ->all();

            $uniqueDestinationCount = collect($destinations)->pluck('outletId')->unique()->count();
            if ($uniqueDestinationCount !== count($destinations)) {
                throw new \RuntimeException('Outlet tujuan transfer tidak boleh duplikat.');
            }

            if ($sourceKind === 'outlet' && collect($destinations)->contains(fn ($row) => $row['outletId'] === $sourceOutletId)) {
                throw new \RuntimeException('Outlet tujuan tidak boleh sama dengan outlet sumber.');
            }

            $branchRows = DB::table('branches')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->whereIn('id', collect($destinations)->pluck('outletId')->all())
                ->get(['id', 'name']);

            if ($branchRows->count() !== count($destinations)) {
                throw new \RuntimeException('Ada outlet tujuan yang tidak ditemukan.');
            }

            if ($resolved['context']['membershipRole'] === 'staff') {
                $allowedSet = array_flip($resolved['allowedBranchIds']);
                $outOfScope = collect($destinations)->contains(fn ($row) => !isset($allowedSet[$row['outletId']]));
                if ($outOfScope) {
                    throw new \RuntimeException('Ada outlet tujuan yang tidak ditemukan.');
                }
            }

            $totalQty = collect($destinations)->sum('qty');

            $sourceLabel = 'Pusat';
            $sourceAfter = 0;

            if ($sourceKind === 'central') {
                $sourceStock = (int) $product->central_stock;
                if ($totalQty > $sourceStock) {
                    throw new \RuntimeException('Transfer gagal. Total jumlah transfer melebihi stok sumber.');
                }

                $sourceAfter = $sourceStock - $totalQty;

                DB::table('inventory_products')
                    ->where('id', $product->id)
                    ->update([
                        'central_stock' => $sourceAfter,
                        'updated_at' => now(),
                    ]);
            } else {
                $sourceBranch = DB::table('branches')
                    ->where('tenant_id', $resolved['context']['tenantId'])
                    ->where('id', $sourceOutletId)
                    ->first(['id', 'name']);

                if (!$sourceBranch) {
                    throw new \RuntimeException('Outlet sumber tidak ditemukan.');
                }

                $sourceLabel = $sourceBranch->name;
                $sourceStockRow = $this->loadBranchStockForUpdate($resolved['context']['tenantId'], $sourceOutletId, $product->id);
                $sourceStock = (int) $sourceStockRow->qty;

                if ($totalQty > $sourceStock) {
                    throw new \RuntimeException('Transfer gagal. Total jumlah transfer melebihi stok sumber.');
                }

                $sourceAfter = $sourceStock - $totalQty;

                DB::table('inventory_branch_stocks')
                    ->where('id', $sourceStockRow->id)
                    ->update([
                        'qty' => $sourceAfter,
                        'updated_at' => now(),
                    ]);
            }

            $branchById = $branchRows->keyBy('id');
            $destinationSnapshots = [];

            foreach ($destinations as $destination) {
                $stockRow = $this->loadBranchStockForUpdate(
                    $resolved['context']['tenantId'],
                    $destination['outletId'],
                    $product->id,
                );
                $nextQty = (int) $stockRow->qty + $destination['qty'];

                DB::table('inventory_branch_stocks')
                    ->where('id', $stockRow->id)
                    ->update([
                        'qty' => $nextQty,
                        'updated_at' => now(),
                    ]);

                $destinationSnapshots[] = [
                    'outletId' => $destination['outletId'],
                    'outletName' => $branchById[$destination['outletId']]->name,
                    'qty' => $destination['qty'],
                    'balanceAfter' => $nextQty,
                ];
            }

            $transferId = (string) Str::uuid();
            DB::table('inventory_transfers')->insert([
                'id' => $transferId,
                'tenant_id' => $resolved['context']['tenantId'],
                'product_id' => $product->id,
                'source_branch_id' => $sourceKind === 'outlet' ? $sourceOutletId : null,
                'source_kind' => $sourceKind,
                'source_label' => $sourceLabel,
                'total_qty' => $totalQty,
                'note' => $note !== '' ? $note : 'Transfer stok',
                'created_by' => $resolved['profileId'],
                'created_at' => now(),
            ]);

            DB::table('inventory_transfer_dests')->insert(array_map(
                static fn (array $destination) => [
                    'id' => (string) Str::uuid(),
                    'transfer_id' => $transferId,
                    'branch_id' => $destination['outletId'],
                    'qty' => $destination['qty'],
                ],
                $destinationSnapshots,
            ));

            $this->insertMovementLog([
                'tenantId' => $resolved['context']['tenantId'],
                'productId' => $product->id,
                'productName' => $product->name,
                'branchId' => $sourceKind === 'outlet' ? $sourceOutletId : null,
                'type' => 'out',
                'qty' => $totalQty,
                'note' => $note !== '' ? $note : 'Transfer keluar',
                'delta' => -$totalQty,
                'balanceAfter' => $sourceAfter,
                'locationKind' => $sourceKind,
                'locationId' => $sourceKind === 'outlet' ? $sourceOutletId : 'central',
                'locationLabel' => $sourceLabel,
                'countedStock' => null,
                'createdBy' => $resolved['profileId'],
            ]);

            foreach ($destinationSnapshots as $destination) {
                $this->insertMovementLog([
                    'tenantId' => $resolved['context']['tenantId'],
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'branchId' => $destination['outletId'],
                    'type' => 'in',
                    'qty' => $destination['qty'],
                    'note' => $note !== '' ? $note : 'Transfer masuk',
                    'delta' => $destination['qty'],
                    'balanceAfter' => $destination['balanceAfter'],
                    'locationKind' => 'outlet',
                    'locationId' => $destination['outletId'],
                    'locationLabel' => $destination['outletName'],
                    'countedStock' => null,
                    'createdBy' => $resolved['profileId'],
                ]);
            }
            }, 3);
        } catch (\RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->ok([
            'ok' => true,
            'message' => 'Transfer produk berhasil disimpan.',
        ]);
    }

    private function resolveInventoryContext(Request $request, string $tenantSlug, bool $requireWritable = false): array|JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $tenantSlug,
            [],
            $requireWritable,
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
            'address',
            'latitude',
            'longitude',
        ]);
    }

    private function canAccessBranch(array $resolved, string $branchId): bool
    {
        if ($resolved['context']['membershipRole'] !== 'staff') {
            return DB::table('branches')
                ->where('tenant_id', $resolved['context']['tenantId'])
                ->where('id', $branchId)
                ->exists();
        }

        $allowed = array_flip($resolved['allowedBranchIds']);

        return isset($allowed[$branchId]);
    }

    private function snapshotPayload(array $context, $allowedBranches): array
    {
        $tenantId = $context['tenantId'];
        $allowedBranchIds = $allowedBranches->pluck('id')->all();

        $categories = DB::table('inventory_categories')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->all();

        $units = DB::table('inventory_units')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->all();

        $products = DB::table('inventory_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'central_stock',
                'minimum_low_stock',
                'category_id',
                'unit_id',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'stock' => (int) $row->central_stock,
                'minimumLowStock' => (int) $row->minimum_low_stock,
                'categoryId' => $row->category_id,
                'unitId' => $row->unit_id,
            ])
            ->all();

        $outlets = $allowedBranches
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'address' => $row->address,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : 0,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : 0,
            ])
            ->values()
            ->all();

        $outletStocks = $allowedBranchIds === []
            ? []
            : DB::table('inventory_branch_stocks')
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', $allowedBranchIds)
                ->get(['branch_id', 'product_id', 'qty'])
                ->map(fn ($row) => [
                    'outletId' => $row->branch_id,
                    'productId' => $row->product_id,
                    'qty' => (int) $row->qty,
                ])
                ->all();

        $movementsQuery = DB::table('inventory_movements')
            ->join('inventory_products', 'inventory_movements.product_id', '=', 'inventory_products.id')
            ->where('inventory_movements.tenant_id', $tenantId);

        if ($context['membershipRole'] === 'staff') {
            $pstBranchId = $allowedBranches->firstWhere('code', 'PST')?->id;

            $movementsQuery->where(function ($query) use ($allowedBranchIds, $pstBranchId) {
                if ($pstBranchId) {
                    $query->where('inventory_movements.location_kind', 'central');
                }

                if ($allowedBranchIds !== []) {
                    $query->orWhere(function ($sub) use ($allowedBranchIds) {
                        $sub->where('inventory_movements.location_kind', 'outlet')
                            ->whereIn('inventory_movements.branch_id', $allowedBranchIds);
                    });
                }

                if (!$pstBranchId && $allowedBranchIds === []) {
                    $query->whereRaw('1=0');
                }
            });
        }

        $movements = $movementsQuery
            ->orderByDesc('inventory_movements.created_at')
            ->get([
                'inventory_movements.id',
                'inventory_movements.product_id',
                'inventory_movements.qty',
                'inventory_movements.type',
                'inventory_movements.note',
                'inventory_movements.delta',
                'inventory_movements.balance_after',
                'inventory_movements.location_kind',
                'inventory_movements.location_id',
                'inventory_movements.location_label',
                'inventory_movements.counted_stock',
                'inventory_movements.created_at',
                'inventory_products.name as product_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'productId' => $row->product_id,
                'productName' => $row->product_name,
                'qty' => (int) $row->qty,
                'type' => $row->type,
                'note' => $row->note,
                'delta' => (int) $row->delta,
                'balanceAfter' => (int) $row->balance_after,
                'locationKind' => $row->location_kind,
                'locationId' => $row->location_id,
                'locationLabel' => $row->location_label,
                'countedStock' => $row->counted_stock !== null ? (int) $row->counted_stock : null,
                'createdAt' => $row->created_at,
            ])
            ->all();

        $transferRows = DB::table('inventory_transfers')
            ->join('inventory_products', 'inventory_transfers.product_id', '=', 'inventory_products.id')
            ->where('inventory_transfers.tenant_id', $tenantId)
            ->orderByDesc('inventory_transfers.created_at')
            ->get([
                'inventory_transfers.id',
                'inventory_transfers.product_id',
                'inventory_transfers.source_kind',
                'inventory_transfers.source_branch_id',
                'inventory_transfers.source_label',
                'inventory_transfers.total_qty',
                'inventory_transfers.note',
                'inventory_transfers.created_at',
                'inventory_products.name as product_name',
            ]);

        $transferIds = $transferRows->pluck('id')->all();

        $destinationRows = $transferIds === []
            ? collect()
            : DB::table('inventory_transfer_dests')
                ->join('branches', 'inventory_transfer_dests.branch_id', '=', 'branches.id')
                ->whereIn('inventory_transfer_dests.transfer_id', $transferIds)
                ->get([
                    'inventory_transfer_dests.transfer_id',
                    'inventory_transfer_dests.branch_id as outlet_id',
                    'inventory_transfer_dests.qty',
                    'branches.name as outlet_name',
                ]);

        $destinationsByTransfer = $destinationRows->groupBy('transfer_id');
        $allowedSet = array_flip($allowedBranchIds);

        $transfers = [];

        foreach ($transferRows as $transferRow) {
            $allDestinations = ($destinationsByTransfer[$transferRow->id] ?? collect())
                ->map(fn ($row) => [
                    'outletId' => $row->outlet_id,
                    'outletName' => $row->outlet_name,
                    'qty' => (int) $row->qty,
                ])
                ->values()
                ->all();

            $visibleDestinations = $allDestinations;

            if ($context['membershipRole'] === 'staff') {
                $pstBranchId = $allowedBranches->firstWhere('code', 'PST')?->id;

                $visibleDestinations = array_values(array_filter(
                    $allDestinations,
                    static fn (array $destination) => isset($allowedSet[$destination['outletId']]),
                ));

                $sourceVisible = false;
                if ($transferRow->source_kind === 'central') {
                    $sourceVisible = (bool) $pstBranchId;
                } else {
                    $sourceVisible = isset($allowedSet[$transferRow->source_branch_id]);
                }

                if (!$sourceVisible && $visibleDestinations === []) {
                    continue;
                }
            }

            $transfers[] = [
                'id' => $transferRow->id,
                'productId' => $transferRow->product_id,
                'productName' => $transferRow->product_name,
                'sourceKind' => $transferRow->source_kind,
                'sourceOutletId' => $transferRow->source_branch_id,
                'sourceLabel' => $transferRow->source_label,
                'totalQty' => (int) $transferRow->total_qty,
                'note' => $transferRow->note,
                'createdAt' => $transferRow->created_at,
                'destinations' => $visibleDestinations,
            ];
        }

        return [
            'categories' => $categories,
            'units' => $units,
            'products' => $products,
            'outlets' => $outlets,
            'outletStocks' => $outletStocks,
            'movements' => $movements,
            'transfers' => $transfers,
        ];
    }

    private function loadBranchStockForUpdate(string $tenantId, string $branchId, string $productId): object
    {
        $stock = DB::table('inventory_branch_stocks')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        DB::table('inventory_branch_stocks')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inventory_branch_stocks')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    private function insertMovementLog(array $payload): void
    {
        DB::table('inventory_movements')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $payload['tenantId'],
            'product_id' => $payload['productId'],
            'branch_id' => $payload['branchId'],
            'type' => $payload['type'],
            'qty' => $payload['qty'],
            'note' => $payload['note'],
            'delta' => $payload['delta'],
            'balance_after' => $payload['balanceAfter'],
            'location_kind' => $payload['locationKind'],
            'location_id' => $payload['locationId'],
            'location_label' => $payload['locationLabel'],
            'counted_stock' => $payload['countedStock'],
            'created_by' => $payload['createdBy'],
            'created_at' => now(),
        ]);
    }
}
