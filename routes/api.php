<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register-owner', [AuthController::class, 'registerOwner']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tenant/context', [TenantController::class, 'context']);
    Route::post('/tenant/staff', [TenantController::class, 'createStaff']);

    Route::post('/billing/invoices', [BillingController::class, 'createInvoice']);
    Route::post('/billing/payments/upload-url', [BillingController::class, 'createUploadUrl']);
    Route::post('/billing/payments/upload', [BillingController::class, 'uploadProof']);
    Route::post('/billing/payments/submit', [BillingController::class, 'submitPayment']);

    Route::get('/platform/payments', [PlatformController::class, 'payments']);
    Route::post('/platform/payments/{submissionId}/approve', [PlatformController::class, 'approvePayment']);
    Route::post('/platform/payments/{submissionId}/reject', [PlatformController::class, 'rejectPayment']);
    Route::get('/platform/plans', [PlatformController::class, 'plans']);
    Route::post('/platform/plans', [PlatformController::class, 'createPlan']);
    Route::patch('/platform/plans', [PlatformController::class, 'updatePlan']);
    Route::get('/platform/tenants', [PlatformController::class, 'tenants']);
});

Route::post('/platform/billing/run-daily', [PlatformController::class, 'runDaily']);
