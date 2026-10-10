<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\DataEntryController;
use App\Http\Controllers\Admin\ReportController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('administrators', AdministratorController::class);
    Route::apiResource('data-entries', DataEntryController::class)->only(['index', 'store']);
    Route::get('reports', [ReportController::class, 'index']);
    Route::post('reports/export', [ReportController::class, 'export']);
});