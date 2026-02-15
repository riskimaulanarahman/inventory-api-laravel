<?php

namespace App\Http\Controllers\Api;

use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformController extends BaseApiController
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    public function runDaily(Request $request): JsonResponse
    {
        $secret = env('CRON_SECRET');
        if ($secret) {
            $provided = $request->header('x-cron-secret');
            if ($provided !== $secret) {
                return $this->error('Secret cron tidak valid.', 401);
            }
        }

        $result = $this->billingService->applyDailyBillingStateTransitions();

        return $this->ok([
            'ok' => true,
            'result' => $result,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $submissions = DB::table('payment_submissions')
            ->join('invoices', 'payment_submissions.invoice_id', '=', 'invoices.id')
            ->join('tenants', 'payment_submissions.tenant_id', '=', 'tenants.id')
            ->orderByDesc('payment_submissions.created_at')
            ->select([
                'payment_submissions.*',
                'invoices.invoice_number',
                'invoices.amount as invoice_amount',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'tenantId' => $row->tenant_id,
                'invoiceId' => $row->invoice_id,
                'proofPath' => $row->proof_path,
                'transferAmount' => $row->transfer_amount,
                'bankName' => $row->bank_name,
                'senderBank' => $row->sender_bank,
                'note' => $row->note,
                'status' => $row->status,
                'reviewedBy' => $row->reviewed_by,
                'reviewedAt' => $row->reviewed_at,
                'createdAt' => $row->created_at,
                'updatedAt' => $row->updated_at,
                'invoice' => [
                    'invoiceNumber' => $row->invoice_number,
                    'amount' => $row->invoice_amount,
                ],
                'tenant' => [
                    'name' => $row->tenant_name,
                    'slug' => $row->tenant_slug,
                ],
            ])
            ->all();

        return $this->ok(['submissions' => $submissions]);
    }

    public function approvePayment(Request $request, string $submissionId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $validated = $this->validateInput($request, [
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $subscription = $this->billingService->approvePaymentSubmission(
                $submissionId,
                $auth['profile']->id,
                $validated['note'] ?? null,
            );

            return $this->ok(['subscription' => $subscription]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function rejectPayment(Request $request, string $submissionId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $validated = $this->validateInput($request, [
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $subscription = $this->billingService->rejectPaymentSubmission(
                $submissionId,
                $auth['profile']->id,
                $validated['note'] ?? null,
            );

            return $this->ok(['subscription' => $subscription]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function plans(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $plans = DB::table('plans')
            ->whereNull('tenant_id')
            ->orderBy('created_at')
            ->get();

        return $this->ok(['plans' => $plans]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $validated = $this->validateInput($request, [
            'code' => ['required', 'string', 'min:2'],
            'name' => ['required', 'string', 'min:2'],
            'description' => ['nullable', 'string'],
            'monthlyPrice' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'yearlyPrice' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $id = (string) Str::uuid();
        $now = now();

        DB::table('plans')->insert([
            'id' => $id,
            'tenant_id' => null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'monthly_price' => $validated['monthlyPrice'],
            'yearly_price' => $validated['yearlyPrice'],
            'is_active' => $validated['isActive'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->ok(['plan' => DB::table('plans')->where('id', $id)->first()]);
    }

    public function updatePlan(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $validated = $this->validateInput($request, [
            'id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'min:2'],
            'description' => ['nullable', 'string'],
            'monthlyPrice' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'yearlyPrice' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $update = [];

        if (array_key_exists('name', $validated)) {
            $update['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $update['description'] = $validated['description'];
        }
        if (array_key_exists('monthlyPrice', $validated)) {
            $update['monthly_price'] = $validated['monthlyPrice'];
        }
        if (array_key_exists('yearlyPrice', $validated)) {
            $update['yearly_price'] = $validated['yearlyPrice'];
        }
        if (array_key_exists('isActive', $validated)) {
            $update['is_active'] = $validated['isActive'];
        }

        if ($update === []) {
            return $this->error('Tidak ada data yang diubah.', 422);
        }

        $update['updated_at'] = now();

        DB::table('plans')->where('id', $validated['id'])->update($update);

        return $this->ok(['plan' => DB::table('plans')->where('id', $validated['id'])->first()]);
    }

    public function deletePlan(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $validated = $this->validateInput($request, [
            'id' => ['required', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        // Only allow deleting platform-wide plans (tenant_id is null)
        $plan = DB::table('plans')->where('id', $validated['id'])->whereNull('tenant_id')->first();
        if (!$plan) {
            return $this->error('Paket tidak ditemukan atau tidak dapat dihapus.', 404);
        }

        DB::table('plans')->where('id', $validated['id'])->delete();

        return $this->ok(['success' => true]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $tenants = DB::table('tenants')->orderByDesc('created_at')->get();

        $result = $tenants->map(function ($tenant) {
            $owner = DB::table('memberships')
                ->join('profiles', 'memberships.profile_id', '=', 'profiles.id')
                ->where('memberships.tenant_id', $tenant->id)
                ->where('memberships.role', 'tenant_owner')
                ->where('memberships.is_active', true)
                ->select('profiles.email', 'profiles.display_name')
                ->first();

            $subscription = DB::table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('updated_at')
                ->first();

            return [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'type' => $tenant->type,
                'status' => $tenant->status,
                'createdAt' => $tenant->created_at,
                'expiryDate' => $subscription ? ($subscription->status === 'trialing' ? $subscription->trial_end_at : $subscription->period_end_at) : null,
                'owner' => $owner ? [
                    'email' => $owner->email,
                    'displayName' => $owner->display_name,
                ] : null,
                'subscription' => $subscription,
                'counts' => [
                    'memberships' => DB::table('memberships')->where('tenant_id', $tenant->id)->count(),
                    'branches' => DB::table('branches')->where('tenant_id', $tenant->id)->count(),
                ],
            ];
        })->all();

        return $this->ok(['tenants' => $result]);
    }

    public function tenantDetails(Request $request, string $tenantId): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($platformError = $this->requirePlatformAdmin($auth['profile']->id)) {
            return $platformError;
        }

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return $this->error('Cabang/Outlet tidak ditemukan.', 404);
        }

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->first();

        $owner = DB::table('memberships')
            ->join('profiles', 'memberships.profile_id', '=', 'profiles.id')
            ->where('memberships.tenant_id', $tenant->id)
            ->where('memberships.role', 'tenant_owner')
            ->select('profiles.email', 'profiles.display_name', 'profiles.phone')
            ->first();

        $branches = DB::table('branches')
            ->where('tenant_id', $tenant->id)
            ->get();

        // Stock Transactions (Movements) - optional, may not exist in all setups
        try {
            $movements = DB::table('inventory_movements')
                ->join('branches', 'inventory_movements.branch_id', '=', 'branches.id')
                ->join('products', 'inventory_movements.product_id', '=', 'products.id')
                ->join('users', 'inventory_movements.created_by', '=', 'users.id')
                ->where('branches.tenant_id', $tenant->id)
                ->orderByDesc('inventory_movements.created_at')
                ->limit(50)
                ->select([
                    'inventory_movements.id',
                    'inventory_movements.type',
                    'inventory_movements.quantity',
                    'inventory_movements.notes',
                    'inventory_movements.created_at',
                    'branches.name as branch_name',
                    'products.name as product_name',
                    'users.name as user_name',
                ])
                ->get();
        } catch (\Exception $e) {
            // If tables don't exist, return empty array
            $movements = collect([]);
        }

        return $this->ok([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'createdAt' => $tenant->created_at,
                'expiryDate' => $subscription ? ($subscription->status === 'trialing' ? $subscription->trial_end_at : $subscription->period_end_at) : null,
            ],
            'owner' => $owner,
            'subscription' => $subscription,
            'branches' => $branches,
            'transactions' => $movements,
        ]);
    }
}
