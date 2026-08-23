<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;

Route::get('health', HealthController::class);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth.api')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('permission:dashboard.view,reports.view');
});
