<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\DataEntryController;
use App\Http\Controllers\Admin\ReportController;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::prefix('administrators')->group(function () {
        Route::get('/', [AdministratorController::class, 'index'])->name('administrators.index');
        Route::get('/create', [AdministratorController::class, 'create'])->name('administrators.create');
        Route::post('/', [AdministratorController::class, 'store'])->name('administrators.store');
        Route::get('/{administrator}/edit', [AdministratorController::class, 'edit'])->name('administrators.edit');
        Route::put('/{administrator}', [AdministratorController::class, 'update'])->name('administrators.update');
        Route::delete('/{administrator}', [AdministratorController::class, 'destroy'])->name('administrators.destroy');
    });

    Route::prefix('data-entries')->group(function () {
        Route::get('/', [DataEntryController::class, 'index'])->name('data_entries.index');
        Route::get('/create', [DataEntryController::class, 'create'])->name('data_entries.create');
        Route::post('/', [DataEntryController::class, 'store'])->name('data_entries.store');
        Route::get('/{dataEntry}/edit', [DataEntryController::class, 'edit'])->name('data_entries.edit');
        Route::put('/{dataEntry}', [DataEntryController::class, 'update'])->name('data_entries.update');
        Route::delete('/{dataEntry}', [DataEntryController::class, 'destroy'])->name('data_entries.destroy');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::prefix('families')->group(function () {
        Route::get('/', [FamilyController::class, 'index'])->name('families.index');
        Route::get('/create', [FamilyController::class, 'create'])->name('families.create');
        Route::post('/', [FamilyController::class, 'store'])->name('families.store');
    });
});
