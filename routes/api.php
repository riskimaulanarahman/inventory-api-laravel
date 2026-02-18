<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\InventoryDashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/public/plans', [PublicController::class, 'plans']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register-owner', [AuthController::class, 'registerOwner']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tenant/context', [TenantController::class, 'context']);
    Route::get('/tenant/staff', [TenantController::class, 'listStaff']);
    Route::post('/tenant/staff', [TenantController::class, 'createStaff']);
    Route::patch('/tenant/staff/{membershipId}', [TenantController::class, 'updateStaff']);
    Route::patch('/tenant/staff/{membershipId}/password-reset', [TenantController::class, 'resetStaffPassword']);
    Route::delete('/tenant/staff/{membershipId}', [TenantController::class, 'deactivateStaff']);

    Route::prefix('inventory/{tenantSlug}')->group(function () {
        Route::get('/snapshot', [InventoryController::class, 'snapshot']);
        Route::get('/dashboard/alerts', [InventoryDashboardController::class, 'alerts']);

        Route::post('/categories', [InventoryController::class, 'createCategory']);
        Route::patch('/categories/{categoryId}', [InventoryController::class, 'updateCategory']);
        Route::delete('/categories/{categoryId}', [InventoryController::class, 'deleteCategory']);

        Route::post('/units', [InventoryController::class, 'createUnit']);
        Route::patch('/units/{unitId}', [InventoryController::class, 'updateUnit']);
        Route::delete('/units/{unitId}', [InventoryController::class, 'deleteUnit']);

        Route::post('/products', [InventoryController::class, 'createProduct']);
        Route::patch('/products/{productId}', [InventoryController::class, 'updateProduct']);
        Route::delete('/products/{productId}', [InventoryController::class, 'deleteProduct']);

        Route::post('/outlets', [InventoryController::class, 'createOutlet']);
        Route::patch('/outlets/{outletId}', [InventoryController::class, 'updateOutlet']);
        Route::delete('/outlets/{outletId}', [InventoryController::class, 'deleteOutlet']);

        Route::post('/movements', [InventoryController::class, 'createMovement']);
        Route::post('/opname', [InventoryController::class, 'createOpname']);
        Route::post('/transfers', [InventoryController::class, 'createTransfer']);
    });

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
    Route::delete('/platform/plans', [PlatformController::class, 'deletePlan']);
    Route::get('/platform/tenants', [PlatformController::class, 'tenants']);
    Route::get('/platform/tenants/{tenantId}', [PlatformController::class, 'tenantDetails']);
    Route::patch('/platform/tenants/{tenantId}/status', [PlatformController::class, 'updateTenantStatus']);
    Route::patch('/platform/tenants/{tenantId}/subscription', [PlatformController::class, 'updateSubscription']);

    Route::get('/platform/users', [PlatformController::class, 'listUsers']);
    Route::post('/platform/users/{profileId}/toggle-status', [PlatformController::class, 'toggleUserStatus']);
});

Route::post('/platform/billing/run-daily', [PlatformController::class, 'runDaily']);
