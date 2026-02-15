<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BillingService
{
    public function ensureDefaultPlans(): void
    {
        $existing = DB::table('plans')->whereNull('tenant_id')->count();
        if ($existing > 0) {
            return;
        }

        $now = now();
        DB::table('plans')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => null,
            'code' => 'standard',
            'name' => 'Standard',
            'description' => 'Paket standar untuk semua outlet/cabang.',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function createTenantInvoice(string $tenantId, string $cycle, ?string $planId = null): object
    {
        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')
            ->first();

        if (!$subscription) {
            throw new RuntimeException('Data langganan tidak ditemukan.');
        }

        $plan = $planId
            ? DB::table('plans')->where('id', $planId)->where('is_active', true)->first()
            : DB::table('plans')->whereNull('tenant_id')->where('is_active', true)->orderBy('created_at')->first();

        if (!$plan) {
            throw new RuntimeException('Paket tidak ditemukan.');
        }

        $id = (string) Str::uuid();
        $now = now();

        DB::table('invoices')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'payment_code' => $this->generatePaymentCode(),
            'cycle' => $cycle,
            'amount' => $cycle === 'monthly' ? $plan->monthly_price : $plan->yearly_price,
            'due_at' => $now->copy()->addDays(7),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('invoices')->where('id', $id)->first();
    }

    public function submitPayment(array $payload): object
    {
        $invoice = DB::table('invoices')
            ->where('id', $payload['invoiceId'])
            ->where('tenant_id', $payload['tenantId'])
            ->where('status', 'pending')
            ->first();

        if (!$invoice) {
            throw new RuntimeException('Invoice tidak ditemukan atau sudah tidak dapat dibayar.');
        }

        $id = (string) Str::uuid();
        $now = now();

        DB::table('payment_submissions')->insert([
            'id' => $id,
            'tenant_id' => $payload['tenantId'],
            'invoice_id' => $payload['invoiceId'],
            'proof_path' => $payload['proofPath'],
            'transfer_amount' => $payload['transferAmount'],
            'bank_name' => $payload['bankName'],
            'sender_bank' => $payload['senderBank'] ?? null,
            'note' => $payload['note'] ?? null,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('payment_submissions')->where('id', $id)->first();
    }

    public function approvePaymentSubmission(string $submissionId, string $reviewerId, ?string $note = null): object
    {
        return DB::transaction(function () use ($submissionId, $reviewerId, $note) {
            $submission = DB::table('payment_submissions')->where('id', $submissionId)->lockForUpdate()->first();

            if (!$submission || $submission->status !== 'pending') {
                throw new RuntimeException('Bukti pembayaran tidak ditemukan atau sudah diproses.');
            }

            $invoice = DB::table('invoices')->where('id', $submission->invoice_id)->lockForUpdate()->first();
            if (!$invoice) {
                throw new RuntimeException('Invoice tidak ditemukan.');
            }

            $now = now();
            $nextPeriodEnd = $this->addCycle(Carbon::parse($now), $invoice->cycle);

            DB::table('payment_submissions')->where('id', $submissionId)->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
                'note' => $note ?? $submission->note,
                'updated_at' => $now,
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'paid',
                'paid_at' => $now,
                'note' => $note ?? $invoice->note,
                'updated_at' => $now,
            ]);

            DB::table('subscriptions')->where('id', $invoice->subscription_id)->update([
                'status' => 'active',
                'current_cycle' => $invoice->cycle,
                'period_start_at' => $now,
                'period_end_at' => $nextPeriodEnd,
                'read_only_mode' => false,
                'updated_at' => $now,
            ]);

            DB::table('subscription_events')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $submission->tenant_id,
                'subscription_id' => $invoice->subscription_id,
                'event_type' => 'payment_approved',
                'created_by' => $reviewerId,
                'payload' => json_encode([
                    'invoiceId' => $invoice->id,
                    'submissionId' => $submission->id,
                    'cycle' => $invoice->cycle,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return DB::table('subscriptions')->where('id', $invoice->subscription_id)->first();
        }, 3);
    }

    public function rejectPaymentSubmission(string $submissionId, string $reviewerId, ?string $note = null): object
    {
        return DB::transaction(function () use ($submissionId, $reviewerId, $note) {
            $submission = DB::table('payment_submissions')->where('id', $submissionId)->lockForUpdate()->first();

            if (!$submission || $submission->status !== 'pending') {
                throw new RuntimeException('Bukti pembayaran tidak ditemukan atau sudah diproses.');
            }

            $invoice = DB::table('invoices')->where('id', $submission->invoice_id)->lockForUpdate()->first();
            if (!$invoice) {
                throw new RuntimeException('Invoice tidak ditemukan.');
            }

            $now = now();

            DB::table('payment_submissions')->where('id', $submissionId)->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
                'note' => $note ?? $submission->note,
                'updated_at' => $now,
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'rejected',
                'rejected_at' => $now,
                'note' => $note ?? $invoice->note,
                'updated_at' => $now,
            ]);

            DB::table('subscriptions')->where('id', $invoice->subscription_id)->update([
                'status' => 'past_due',
                'read_only_mode' => true,
                'updated_at' => $now,
            ]);

            DB::table('subscription_events')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $submission->tenant_id,
                'subscription_id' => $invoice->subscription_id,
                'event_type' => 'payment_rejected',
                'created_by' => $reviewerId,
                'payload' => json_encode([
                    'invoiceId' => $invoice->id,
                    'submissionId' => $submission->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return DB::table('subscriptions')->where('id', $invoice->subscription_id)->first();
        }, 3);
    }

    public function applyDailyBillingStateTransitions(?Carbon $now = null): array
    {
        $at = $now ?? now();

        $trialExpiredCount = DB::table('subscriptions')
            ->where('status', 'trialing')
            ->where('trial_end_at', '<', $at)
            ->update([
                'status' => 'expired',
                'read_only_mode' => true,
                'updated_at' => $at,
            ]);

        $expiredInvoiceCount = DB::table('invoices')
            ->where('status', 'pending')
            ->where('due_at', '<', $at)
            ->update([
                'status' => 'expired',
                'updated_at' => $at,
            ]);

        return [
            'trialExpiredCount' => $trialExpiredCount,
            'expiredInvoiceCount' => $expiredInvoiceCount,
        ];
    }

    public function syncTenantBillingState(string $tenantId, ?Carbon $now = null): void
    {
        $at = $now ?? now();

        DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'trialing')
            ->where('trial_end_at', '<', $at)
            ->update([
                'status' => 'expired',
                'read_only_mode' => true,
                'updated_at' => $at,
            ]);
    }

    public function createUploadIntent(string $tenantId, ?string $ext = null): array
    {
        $extension = strtolower((string) ($ext ?: 'jpg'));
        if (!preg_match('/^[a-z0-9]+$/', $extension)) {
            $extension = 'jpg';
        }

        $path = 'payment-proofs/'.$tenantId.'/'.now()->timestamp.'-'.Str::uuid().'.'.$extension;
        $uploadToken = Str::random(64);

        Cache::put('upload-intent:'.$uploadToken, [
            'tenant_id' => $tenantId,
            'path' => $path,
        ], now()->addMinutes(10));

        return [
            'path' => $path,
            'uploadToken' => $uploadToken,
            'uploadUrl' => '/api/billing/payments/upload',
        ];
    }

    public function storeUploadedProof(string $uploadToken, UploadedFile $file): array
    {
        $intent = Cache::pull('upload-intent:'.$uploadToken);

        if (!is_array($intent) || empty($intent['path'])) {
            throw new RuntimeException('Token unggah tidak valid atau sudah kadaluarsa.');
        }

        Storage::disk('public')->put($intent['path'], file_get_contents($file->getRealPath()));

        return [
            'path' => $intent['path'],
            'url' => Storage::disk('public')->url($intent['path']),
        ];
    }

    private function addCycle(Carbon $from, string $cycle): Carbon
    {
        $next = $from->copy();
        if ($cycle === 'monthly') {
            return $next->addMonth();
        }

        return $next->addYear();
    }

    private function generateInvoiceNumber(): string
    {
        $now = now();

        return sprintf(
            'INV-%s%s%s-%06d',
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            random_int(0, 999999),
        );
    }

    private function generatePaymentCode(): string
    {
        return (string) random_int(10000000, 99999999);
    }
}
