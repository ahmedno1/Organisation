<?php

use App\Http\Controllers\Api\V1\FamilyApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('/families', [FamilyApiController::class, 'index']);
    Route::get('/families/{family}', [FamilyApiController::class, 'show']);
});
