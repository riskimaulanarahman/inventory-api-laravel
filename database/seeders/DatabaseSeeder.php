<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (!$email || !$password) {
            app(BillingService::class)->ensureDefaultPlans();
            return;
        }

        $now = now();

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Platform Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }

        DB::table('profiles')->updateOrInsert(
            ['id' => $user->id],
            [
                'email' => $email,
                'display_name' => 'Platform Admin',
                'is_active' => true,
                'must_reset_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('platform_admins')->updateOrInsert(
            ['profile_id' => $user->id],
            [
                'id' => DB::table('platform_admins')->where('profile_id', $user->id)->value('id') ?? (string) Str::uuid(),
                'created_at' => $now,
            ],
        );

        app(BillingService::class)->ensureDefaultPlans();
    }
}
