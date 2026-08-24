<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlaceAssignmentController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SpreadsheetController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\RentObligationController;
use App\Http\Controllers\Api\RentPeriodController;
use App\Http\Controllers\Api\RentRateController;
use App\Http\Controllers\Api\UserController;

Route::get('health', HealthController::class);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth.api')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('permission:dashboard.view,reports.view');

    Route::middleware('permission:roles.manage')->group(function (): void {
        Route::apiResource('roles', RoleController::class);
    });

    Route::middleware('permission:permissions.manage')->group(function (): void {
        Route::apiResource('permissions', PermissionController::class);
    });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::apiResource('users', UserController::class);
    });

    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::patch('settings', [SettingController::class, 'update']);
    });

    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('movements', [MovementController::class, 'index']);
        Route::get('movements/{movement}', [MovementController::class, 'show']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });

    Route::middleware('permission:blocks.manage')->group(function (): void {
        Route::apiResource('blocks', BlockController::class);
    });

    Route::middleware('permission:places.manage')->group(function (): void {
        Route::apiResource('places', PlaceController::class);
    });

    Route::middleware('permission:merchants.manage')->group(function (): void {
        Route::apiResource('merchants', MerchantController::class);
    });

    Route::middleware('permission:banks.manage')->group(function (): void {
        Route::apiResource('banks', BankController::class);
    });

    Route::middleware('permission:assignments.manage')->group(function (): void {
        Route::apiResource('assignments', PlaceAssignmentController::class);
        Route::post('assignments/{assignment}/terminate', [PlaceAssignmentController::class, 'terminate']);
    });

    Route::middleware('permission:rents.manage')->group(function (): void {
        Route::apiResource('rent-rates', RentRateController::class);
        Route::apiResource('rent-periods', RentPeriodController::class);
        Route::post('rent-periods/{rentPeriod}/generate-obligations', [RentPeriodController::class, 'generateObligations']);
        Route::apiResource('rent-obligations', RentObligationController::class)->only(['index', 'show', 'update']);
    });

    Route::middleware('permission:payments.manage')->group(function (): void {
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
        Route::post('payments/preview-allocation', [PaymentController::class, 'previewAllocation']);
        Route::post('payments/{payment}/void', [PaymentController::class, 'void']);
    });

    Route::middleware('permission:receipts.manage')->group(function (): void {
        Route::apiResource('receipts', ReceiptController::class)->only(['index', 'show']);
        Route::post('receipts/{receipt}/cancel', [ReceiptController::class, 'cancel']);
    });

    Route::middleware('permission:exports.manage')->group(function (): void {
        Route::get('exports/excel', [SpreadsheetController::class, 'export']);
    });

    Route::middleware('permission:imports.manage')->group(function (): void {
        Route::post('imports/excel', [SpreadsheetController::class, 'import']);
        Route::get('imports/template-excel', [SpreadsheetController::class, 'template']);
    });
});
