<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_for_new_owner_is_empty_and_hides_pst_branch(): void
    {
        [$token, $tenantSlug] = $this->registerAndLoginOwner();

        $response = $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot");

        $response
            ->assertOk()
            ->assertJson([
                'categories' => [],
                'units' => [],
                'products' => [],
                'outlets' => [],
                'outletStocks' => [],
                'transfers' => [],
            ]);
    }

    public function test_can_create_master_data_and_reject_invalid_stock_out(): void
    {
        [$token, $tenantSlug] = $this->registerAndLoginOwner();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/categories", [
                'name' => 'ATK',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/units", [
                'name' => 'Pcs',
            ])
            ->assertOk();

        $snapshot = $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk()
            ->json();

        $categoryId = $snapshot['categories'][0]['id'];
        $unitId = $snapshot['units'][0]['id'];

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/products", [
                'name' => 'Produk A',
                'sku' => 'SKU-A',
                'initialStock' => 10,
                'minimumLowStock' => 2,
                'categoryId' => $categoryId,
                'unitId' => $unitId,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/movements", [
                'productId' => $this->withToken($token)
                    ->getJson("/api/inventory/{$tenantSlug}/snapshot")
                    ->json('products.0.id'),
                'qty' => 999,
                'type' => 'out',
                'note' => 'invalid out',
                'location' => [
                    'kind' => 'central',
                ],
            ])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/movements", [
                'productId' => $this->withToken($token)
                    ->getJson("/api/inventory/{$tenantSlug}/snapshot")
                    ->json('products.0.id'),
                'qty' => 3,
                'type' => 'out',
                'note' => 'valid out',
                'location' => [
                    'kind' => 'central',
                ],
            ])
            ->assertOk();

        $finalSnapshot = $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk();

        $finalSnapshot->assertJsonPath('products.0.stock', 7);
    }

    public function test_dashboard_alerts_returns_multilocation_low_stock_data(): void
    {
        [$token, $tenantSlug] = $this->registerAndLoginOwner();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/categories", [
                'name' => 'ATK',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/units", [
                'name' => 'Pcs',
            ])
            ->assertOk();

        $masterSnapshot = $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk()
            ->json();

        $categoryId = $masterSnapshot['categories'][0]['id'];
        $unitId = $masterSnapshot['units'][0]['id'];

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/products", [
                'name' => 'Produk A',
                'sku' => 'SKU-A',
                'initialStock' => 2,
                'minimumLowStock' => 5,
                'categoryId' => $categoryId,
                'unitId' => $unitId,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/products", [
                'name' => 'Produk B',
                'sku' => 'SKU-B',
                'initialStock' => 10,
                'minimumLowStock' => 2,
                'categoryId' => $categoryId,
                'unitId' => $unitId,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/products", [
                'name' => 'Produk C',
                'sku' => 'SKU-C',
                'initialStock' => 10,
                'minimumLowStock' => 4,
                'categoryId' => $categoryId,
                'unitId' => $unitId,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/outlets", [
                'name' => 'Outlet Kebon Jeruk',
                'code' => 'KBJ',
                'address' => 'Jl. Kebon Jeruk',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ])
            ->assertOk();

        $snapshot = $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk()
            ->json();

        $outletId = $snapshot['outlets'][0]['id'];
        $productIdBySku = collect($snapshot['products'])->mapWithKeys(
            fn (array $product) => [$product['sku'] => $product['id']],
        );

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/transfers", [
                'productId' => $productIdBySku['SKU-B'],
                'source' => [
                    'kind' => 'central',
                ],
                'destinations' => [
                    [
                        'outletId' => $outletId,
                        'qty' => 1,
                    ],
                ],
                'note' => 'Transfer B',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/transfers", [
                'productId' => $productIdBySku['SKU-C'],
                'source' => [
                    'kind' => 'central',
                ],
                'destinations' => [
                    [
                        'outletId' => $outletId,
                        'qty' => 2,
                    ],
                ],
                'note' => 'Transfer C',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/inventory/{$tenantSlug}/movements", [
                'productId' => $productIdBySku['SKU-C'],
                'qty' => 2,
                'type' => 'out',
                'note' => 'Keluarkan stok outlet C',
                'location' => [
                    'kind' => 'outlet',
                    'outletId' => $outletId,
                ],
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/dashboard/alerts?location=central&limit=5")
            ->assertOk()
            ->assertJsonPath('locationFilter', 'central')
            ->assertJsonPath('lowStockCount', 1)
            ->assertJsonPath('lowStockPriorities.0.sku', 'SKU-A')
            ->assertJsonPath('lowStockPriorities.0.locationKind', 'central')
            ->assertJsonPath('lowStockPriorities.0.locationKey', 'central');

        $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/dashboard/alerts?location=outlet:{$outletId}&limit=5")
            ->assertOk()
            ->assertJsonPath("locationFilter", "outlet:{$outletId}")
            ->assertJsonPath('lowStockCount', 2)
            ->assertJsonPath('lowStockPriorities.0.sku', 'SKU-C')
            ->assertJsonPath('lowStockPriorities.0.currentStock', 0)
            ->assertJsonPath('lowStockPriorities.0.locationKind', 'outlet')
            ->assertJsonPath('lowStockPriorities.0.outletId', $outletId);

        $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/dashboard/alerts?location=all&limit=5")
            ->assertOk()
            ->assertJsonPath('locationFilter', 'all')
            ->assertJsonPath('lowStockCount', 3)
            ->assertJsonPath('lowStockPriorities.0.sku', 'SKU-C')
            ->assertJsonPath('lowStockPriorities.1.sku', 'SKU-A')
            ->assertJsonPath('lowStockPriorities.2.sku', 'SKU-B')
            ->assertJsonPath('lowStockPriorities.2.locationKey', "outlet:{$outletId}");
    }

    public function test_dashboard_alerts_rejects_unknown_outlet_filter(): void
    {
        [$token, $tenantSlug] = $this->registerAndLoginOwner();
        $unknownOutletId = (string) Str::uuid();

        $this->withToken($token)
            ->getJson("/api/inventory/{$tenantSlug}/dashboard/alerts?location=outlet:{$unknownOutletId}")
            ->assertStatus(422);
    }

    public function test_can_update_profile_and_change_password_without_old_password(): void
    {
        [$token, $tenantSlug, $email, $password] = $this->registerAndLoginOwnerDetailed();

        $this->withToken($token)
            ->patchJson('/api/auth/profile', [
                'displayName' => 'Owner Baru',
                'phone' => '081299000123',
            ])
            ->assertOk()
            ->assertJsonPath('profile.displayName', 'Owner Baru')
            ->assertJsonPath('profile.phone', '081299000123')
            ->assertJsonPath('profile.email', $email);

        $this->withToken($token)
            ->patchJson('/api/auth/password', [
                'newPassword' => 'newpassword123',
                'newPasswordConfirmation' => 'newpassword123',
            ])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertStatus(401);

        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_owner_can_crud_staff_and_soft_deactivate_membership(): void
    {
        [$ownerToken, $tenantSlug] = $this->registerAndLoginOwner();

        $this->withToken($ownerToken)
            ->postJson("/api/inventory/{$tenantSlug}/outlets", [
                'name' => 'Outlet A',
                'code' => 'OUTA',
                'address' => 'Alamat Outlet A',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ])
            ->assertOk();

        $this->withToken($ownerToken)
            ->postJson("/api/inventory/{$tenantSlug}/outlets", [
                'name' => 'Outlet B',
                'code' => 'OUTB',
                'address' => 'Alamat Outlet B',
                'latitude' => -6.21,
                'longitude' => 106.81,
            ])
            ->assertOk();

        $snapshot = $this->withToken($ownerToken)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk()
            ->json();

        $outletAId = $snapshot['outlets'][0]['id'];
        $outletBId = $snapshot['outlets'][1]['id'];

        $created = $this->withToken($ownerToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'staff-one@example.com',
                'displayName' => 'Staff One',
                'temporaryPassword' => 'password123',
                'branchIds' => [$outletAId],
            ])
            ->assertOk()
            ->assertJsonPath('staff.role', 'staff')
            ->assertJsonPath('staff.displayName', 'Staff One')
            ->assertJsonPath('staff.branchIds.0', $outletAId)
            ->json('staff');

        $membershipId = $created['membershipId'];

        $this->withToken($ownerToken)
            ->getJson("/api/tenant/staff?tenantSlug={$tenantSlug}")
            ->assertOk()
            ->assertJsonCount(1, 'staff');

        $this->withToken($ownerToken)
            ->patchJson("/api/tenant/staff/{$membershipId}", [
                'tenantSlug' => $tenantSlug,
                'displayName' => 'Staff Satu',
                'branchIds' => [$outletAId, $outletBId],
            ])
            ->assertOk()
            ->assertJsonPath('staff.displayName', 'Staff Satu')
            ->assertJsonCount(2, 'staff.branchIds');

        $this->withToken($ownerToken)
            ->patchJson("/api/tenant/staff/{$membershipId}/password-reset", [
                'tenantSlug' => $tenantSlug,
                'temporaryPassword' => 'newpass123',
            ])
            ->assertOk();

        $this->withToken($ownerToken)
            ->deleteJson("/api/tenant/staff/{$membershipId}?tenantSlug={$tenantSlug}")
            ->assertOk();

        $this->withToken($ownerToken)
            ->getJson("/api/tenant/staff?tenantSlug={$tenantSlug}")
            ->assertOk()
            ->assertJsonCount(0, 'staff');
    }

    public function test_owner_cannot_assign_pst_or_branch_from_other_tenant(): void
    {
        [$ownerToken, $tenantSlug] = $this->registerAndLoginOwner();
        [, $otherTenantSlug] = $this->registerAndLoginOwner();

        $this->withToken($ownerToken)
            ->postJson("/api/inventory/{$tenantSlug}/outlets", [
                'name' => 'Outlet Utama',
                'code' => 'UTM',
                'address' => 'Alamat UTM',
                'latitude' => -6.22,
                'longitude' => 106.82,
            ])
            ->assertOk();

        $otherTenantId = DB::table('tenants')->where('slug', $otherTenantSlug)->value('id');
        $otherOutletId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $otherOutletId,
            'tenant_id' => $otherTenantId,
            'name' => 'Outlet Lain',
            'code' => 'OTR',
            'address' => 'Alamat OTR',
            'latitude' => -6.23,
            'longitude' => 106.83,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantId = DB::table('tenants')->where('slug', $tenantSlug)->value('id');
        $pstBranchId = DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->where('code', 'PST')
            ->value('id');

        $this->withToken($ownerToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'staff-pst@example.com',
                'displayName' => 'Staff PST',
                'temporaryPassword' => 'password123',
                'branchIds' => [$pstBranchId],
            ])
            ->assertOk();

        $this->withToken($ownerToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'staff-other@example.com',
                'displayName' => 'Staff Other',
                'temporaryPassword' => 'password123',
                'branchIds' => [$otherOutletId],
            ])
            ->assertStatus(422);
    }

    public function test_non_owner_roles_cannot_manage_staff(): void
    {
        [$ownerToken, $tenantSlug] = $this->registerAndLoginOwner();

        $this->withToken($ownerToken)
            ->postJson("/api/inventory/{$tenantSlug}/outlets", [
                'name' => 'Outlet Main',
                'code' => 'OMN',
                'address' => 'Alamat OMN',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ])
            ->assertOk();

        $snapshot = $this->withToken($ownerToken)
            ->getJson("/api/inventory/{$tenantSlug}/snapshot")
            ->assertOk()
            ->json();
        $outletId = $snapshot['outlets'][0]['id'];

        $this->withToken($ownerToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'staff-forbidden@example.com',
                'displayName' => 'Staff Forbidden',
                'temporaryPassword' => 'password123',
                'branchIds' => [$outletId],
            ])
            ->assertOk();

        $tenantId = DB::table('tenants')->where('slug', $tenantSlug)->value('id');
        $ownerProfileId = DB::table('memberships')
            ->where('tenant_id', $tenantId)
            ->where('role', 'tenant_owner')
            ->value('profile_id');
        $staffProfileId = DB::table('profiles')
            ->where('email', 'staff-forbidden@example.com')
            ->value('id');
        $staffRole = DB::table('memberships')
            ->where('tenant_id', $tenantId)
            ->where('profile_id', $staffProfileId)
            ->value('role');
        $staffRoles = DB::table('memberships')
            ->where('tenant_id', $tenantId)
            ->where('profile_id', $staffProfileId)
            ->pluck('role')
            ->all();

        $this->assertNotNull($staffProfileId);
        $this->assertNotSame($ownerProfileId, $staffProfileId);
        $this->assertSame('staff', $staffRole);
        $this->assertSame(['staff'], $staffRoles);

        $staffLogin = $this->postJson('/api/auth/login', [
            'email' => 'staff-forbidden@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('user.email', 'staff-forbidden@example.com')
            ->assertJsonPath('profile.id', $staffProfileId);

        $staffToken = $staffLogin->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)
            ->getJson("/api/tenant/staff?tenantSlug={$tenantSlug}")
            ->assertStatus(403);

        $adminId = (string) Str::uuid();
        User::query()->create([
            'id' => $adminId,
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
        ]);

        DB::table('profiles')->insert([
            'id' => $adminId,
            'email' => 'tenant-admin@example.com',
            'display_name' => 'Tenant Admin',
            'phone' => null,
            'must_reset_password' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $membershipId = (string) Str::uuid();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'tenant_id' => $tenantId,
            'profile_id' => $adminId,
            'role' => 'tenant_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('membership_branch_access')->insert([
            'id' => (string) Str::uuid(),
            'membership_id' => $membershipId,
            'branch_id' => $outletId,
            'created_at' => now(),
        ]);

        $adminToken = $this->postJson('/api/auth/login', [
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
        ])->assertOk()->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)
            ->getJson("/api/tenant/staff?tenantSlug={$tenantSlug}")
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'blocked-create@example.com',
                'displayName' => 'Blocked Create',
                'temporaryPassword' => 'password123',
                'branchIds' => [$outletId],
            ])
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)
            ->postJson('/api/tenant/staff', [
                'tenantSlug' => $tenantSlug,
                'email' => 'blocked-create-admin@example.com',
                'displayName' => 'Blocked Create Admin',
                'temporaryPassword' => 'password123',
                'branchIds' => [$outletId],
            ])
            ->assertStatus(403);
    }

    /**
     * @return array{string,string}
     */
    private function registerAndLoginOwner(): array
    {
        [$token, $tenantSlug] = $this->registerAndLoginOwnerDetailed();

        return [$token, $tenantSlug];
    }

    /**
     * @return array{string,string,string,string}
     */
    private function registerAndLoginOwnerDetailed(): array
    {
        $email = sprintf('owner-%s@example.com', uniqid('', true));
        $password = 'password123';

        $register = $this->postJson('/api/auth/register-owner', [
            'email' => $email,
            'password' => $password,
            'displayName' => 'Owner Test',
            'tenantName' => 'Tenant Test',
            'tenantType' => 'company',
            'phone' => '08123456789',
        ])->assertOk();

        $tenantSlug = $register->json('tenantSlug');

        $login = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        return [$login->json('token'), $tenantSlug, $email, $password];
    }
}
