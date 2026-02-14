<?php

namespace App\Http\Controllers\Api;

use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends BaseApiController
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'cycle' => ['required', 'in:monthly,yearly'],
            'planId' => ['nullable', 'uuid'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner', 'tenant_admin'],
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $invoice = $this->billingService->createTenantInvoice(
                $access['context']['tenantId'],
                $validated['cycle'],
                $validated['planId'] ?? null,
            );

            return $this->ok(['invoice' => $invoice]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function createUploadUrl(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'ext' => ['nullable', 'regex:/^[a-zA-Z0-9]+$/'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner', 'tenant_admin'],
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        $intent = $this->billingService->createUploadIntent(
            $access['context']['tenantId'],
            $validated['ext'] ?? null,
        );

        return $this->ok($intent);
    }

    public function uploadProof(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request, [
            'uploadToken' => ['required', 'string', 'size:64'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $result = $this->billingService->storeUploadedProof($validated['uploadToken'], $request->file('file'));
            return $this->ok($result);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }

    public function submitPayment(Request $request): JsonResponse
    {
        $auth = $this->authContext($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validated = $this->validateInput($request, [
            'tenantSlug' => ['required', 'string', 'min:1'],
            'invoiceId' => ['required', 'uuid'],
            'proofPath' => ['required', 'string', 'min:3'],
            'transferAmount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'bankName' => ['required', 'string', 'min:2'],
            'senderBank' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $access = $this->loadTenantAccess(
            $auth['profile']->id,
            $validated['tenantSlug'],
            ['tenant_owner', 'tenant_admin'],
        );

        if ($access instanceof JsonResponse) {
            return $access;
        }

        try {
            $submission = $this->billingService->submitPayment([
                'tenantId' => $access['context']['tenantId'],
                'invoiceId' => $validated['invoiceId'],
                'proofPath' => $validated['proofPath'],
                'transferAmount' => $validated['transferAmount'],
                'bankName' => $validated['bankName'],
                'senderBank' => $validated['senderBank'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return $this->ok(['submission' => $submission]);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 400);
        }
    }
}
