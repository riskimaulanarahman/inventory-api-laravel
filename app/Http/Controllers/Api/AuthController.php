<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends BaseApiController
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (!$user) {
            return $this->error('Email belum terdaftar. Silakan daftar akun terlebih dahulu.', 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return $this->error('Email atau password salah. Silakan coba lagi.', 401);
        }

        $profile = DB::table('profiles')->where('id', $user->id)->first();
        if (!$profile || !$profile->is_active) {
            return $this->error('Akun Anda belum aktif atau tidak memiliki akses.', 403);
        }

        $isPlatformAdmin = DB::table('platform_admins')
            ->where('profile_id', $profile->id)
            ->exists();

        $token = $user->createToken('web')->plainTextToken;

        // Trigger billing state transition check
        $membership = DB::table('memberships')->where('profile_id', $profile->id)->first();
        if ($membership) {
            $this->billingService->syncTenantBillingState($membership->tenant_id);
        }

        return $this->ok([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'profile' => [
                'id' => $profile->id,
                'email' => $profile->email,
                'displayName' => $profile->display_name,
                'phone' => $profile->phone,
                'mustResetPassword' => (bool) $profile->must_reset_password,
                'isActive' => (bool) $profile->is_active,
            ],
            'isPlatformAdmin' => $isPlatformAdmin,
        ]);
    }

    public function registerOwner(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'displayName' => ['required', 'string', 'min:2'],
            'tenantName' => ['required', 'string', 'min:2'],
            'tenantType' => ['required', 'in:company,individual'],
            'phone' => ['nullable', 'string'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $tenantSlug = DB::transaction(function () use ($validated) {
                $now = now();
                $userId = (string) Str::uuid();
                $tenantId = (string) Str::uuid();
                $membershipId = (string) Str::uuid();
                $subscriptionId = (string) Str::uuid();

                User::query()->create([
                    'id' => $userId,
                    'name' => $validated['displayName'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                DB::table('profiles')->insert([
                    'id' => $userId,
                    'email' => $validated['email'],
                    'display_name' => $validated['displayName'],
                    'phone' => $validated['phone'] ?? null,
                    'must_reset_password' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $slug = $this->ensureUniqueTenantSlug($this->toSlug($validated['tenantName']));

                DB::table('tenants')->insert([
                    'id' => $tenantId,
                    'slug' => $slug,
                    'type' => $validated['tenantType'],
                    'name' => $validated['tenantName'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'active',
                    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('memberships')->insert([
                    'id' => $membershipId,
                    'tenant_id' => $tenantId,
                    'profile_id' => $userId,
                    'role' => 'tenant_owner',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('branches')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Pusat',
                    'code' => 'PST',
                    'address' => 'Lokasi pusat',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $trialStart = $now->copy();
                $trialEnd = $trialStart->copy()->addDays(30);

                DB::table('subscriptions')->insert([
                    'id' => $subscriptionId,
                    'tenant_id' => $tenantId,
                    'status' => 'trialing',
                    'trial_start_at' => $trialStart,
                    'trial_end_at' => $trialEnd,
                    'read_only_mode' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('usage_daily')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'day' => $now->toDateString(),
                    'login_count' => 0,
                    'movement_count' => 0,
                    'transfer_count' => 0,
                    'active_user_count' => 1,
                ]);

                DB::table('subscription_events')->insert([
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'subscription_id' => $subscriptionId,
                        'event_type' => 'trial_started',
                        'created_by' => $userId,
                        'payload' => json_encode([
                            'trialStartAt' => $trialStart->toIso8601String(),
                            'trialEndAt' => $trialEnd->toIso8601String(),
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'subscription_id' => $subscriptionId,
                        'event_type' => 'owner_registered',
                        'created_by' => $userId,
                        'payload' => json_encode([
                            'membershipId' => $membershipId,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                    ],
                ]);

                $this->billingService->ensureDefaultPlans();

                return $slug;
            }, 3);

            return $this->ok([
                'ok' => true,
                'tenantSlug' => $tenantSlug,
                'message' => 'Owner registered. Silakan login.',
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return $this->error('Email sudah terdaftar. Silakan gunakan email lain atau login.', 422);
            }

            return $this->error('Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.', 500);
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage(), 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $isPlatformAdmin = DB::table('platform_admins')
            ->where('profile_id', $auth['profile']->id)
            ->exists();

        // Trigger billing state transition check
        $membership = DB::table('memberships')->where('profile_id', $auth['profile']->id)->first();
        if ($membership) {
            $this->billingService->syncTenantBillingState($membership->tenant_id);
        }

        return $this->ok([
            'user' => [
                'id' => $auth['user']->id,
                'email' => $auth['user']->email,
                'name' => $auth['user']->name,
            ],
            'profile' => [
                'id' => $auth['profile']->id,
                'email' => $auth['profile']->email,
                'displayName' => $auth['profile']->display_name,
                'phone' => $auth['profile']->phone,
                'mustResetPassword' => (bool) $auth['profile']->must_reset_password,
                'isActive' => (bool) $auth['profile']->is_active,
            ],
            'isPlatformAdmin' => $isPlatformAdmin,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'displayName' => ['required', 'string', 'min:2'],
            'phone' => ['nullable', 'string'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $displayName = trim($validated['displayName']);

        if ($displayName === '') {
            return $this->error('Nama profil wajib diisi.', 422);
        }

        DB::transaction(function () use ($auth, $displayName, $validated) {
            DB::table('profiles')
                ->where('id', $auth['profile']->id)
                ->update([
                    'display_name' => $displayName,
                    'phone' => $validated['phone'] ?? null,
                    'updated_at' => now(),
                ]);

            User::query()
                ->where('id', $auth['user']->id)
                ->update([
                    'name' => $displayName,
                ]);
        }, 3);

        $profile = DB::table('profiles')->where('id', $auth['profile']->id)->first();

        return $this->ok([
            'profile' => [
                'id' => $profile->id,
                'email' => $profile->email,
                'displayName' => $profile->display_name,
                'phone' => $profile->phone,
                'mustResetPassword' => (bool) $profile->must_reset_password,
                'isActive' => (bool) $profile->is_active,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'newPassword' => ['required', 'string', 'min:8'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        DB::transaction(function () use ($auth, $validated) {
            User::query()
                ->where('id', $auth['user']->id)
                ->update([
                    'password' => Hash::make($validated['newPassword']),
                ]);

            DB::table('profiles')
                ->where('id', $auth['profile']->id)
                ->update([
                    'must_reset_password' => false,
                    'updated_at' => now(),
                ]);
        }, 3);

        return $this->ok([
            'ok' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Sesi Anda telah berakhir. Silakan login kembali.', 401);
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $this->ok(['ok' => true]);
    }

    private function toSlug(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->substr(0, 48)
            ->value() ?: 'tenant';
    }

    private function ensureUniqueTenantSlug(string $base): string
    {
        $normalized = $base !== '' ? $base : 'tenant';
        $candidate = $normalized;

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $exists = DB::table('tenants')->where('slug', $candidate)->exists();
            if (!$exists) {
                return $candidate;
            }

            $suffix = $attempt.'-'.random_int(1, 99);
            $candidate = Str::limit($normalized.'-'.$suffix, 63, '');
        }

        throw new \RuntimeException('Gagal membuat identitas unik untuk Cabang/Outlet.');
    }
}
