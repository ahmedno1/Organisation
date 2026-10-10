<?php

use App\Http\Controllers\Admin\DataEntryUserController;
use App\Http\Controllers\Admin\SecurityController as AdminSecurityController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BenefitController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Developer\AdministratorController;
use App\Http\Controllers\Developer\HealthCheckController;
use App\Http\Controllers\Developer\SecurityController as DeveloperSecurityController;
use App\Http\Controllers\Developer\UserPermissionController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyAttachmentController;
use App\Http\Controllers\FamilyImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InstitutionLogoController;
use App\Http\Controllers\UserSessionController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

$resolveAppApk = function (): ?array {
    $apkCandidates = [
        public_path('downloads/esnad-app-debug.apk'),
        public_path('downloads/esnad-app-release.apk'),
        public_path('downloads/app-debug.apk'),
        public_path('downloads/app-release-unsigned.apk'),
    ];

    foreach ($apkCandidates as $apkPath) {
        if (is_file($apkPath)) {
            return [
                'path' => $apkPath,
                'name' => basename($apkPath),
                'size' => filesize($apkPath) ?: 0,
                'updated_at' => filemtime($apkPath) ?: null,
            ];
        }
    }

    return null;
};

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/locale/{locale}', function (string $locale): RedirectResponse {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.switch');

Route::get('/institutions/{institution}/logo', [InstitutionLogoController::class, 'show'])
    ->name('institutions.logo');

Route::get('/download-app', function () use ($resolveAppApk) {
    $apk = $resolveAppApk();
    $downloadUrl = route('app.download');

    return view('download_app', [
        'apkAvailable' => $apk !== null,
        'apkName' => $apk['name'] ?? null,
        'apkSizeMb' => $apk ? round(((int) $apk['size']) / (1024 * 1024), 2) : null,
        'apkUpdatedAt' => isset($apk['updated_at']) ? date('Y-m-d H:i', (int) $apk['updated_at']) : null,
        'downloadUrl' => $downloadUrl,
        'qrUrl' => 'https://quickchart.io/qr?size=280&text='.rawurlencode($downloadUrl),
    ]);
})->name('app.download.page');

Route::get('/app-download', function () use ($resolveAppApk) {
    $apk = $resolveAppApk();
    if (! $apk) {
        abort(404, 'Application package is not available yet.');
    }

    return response()->download($apk['path'], $apk['name'], [
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Pragma' => 'no-cache',
    ]);
})->name('app.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/two-factor', [AuthController::class, 'showTwoFactorChallenge'])->name('two_factor.challenge');
    Route::post('/two-factor', [AuthController::class, 'verifyTwoFactor'])->name('two_factor.verify');
});

Route::middleware(['auth', 'developer.2fa.setup'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware('role:developer')->group(function () {
        Route::get('/security/two-factor/setup', [AuthController::class, 'showTwoFactorSetup'])->name('two_factor.setup');
        Route::post('/security/two-factor/setup', [AuthController::class, 'enableTwoFactor'])->name('two_factor.setup.store');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:developer,admin,data_entry')->group(function () {
        Route::get('/messages', [ChatController::class, 'index'])->name('messages.index');
        Route::get('/messages/thread/{user}', [ChatController::class, 'thread'])->name('messages.thread');
        Route::post('/messages', [ChatController::class, 'store'])->name('messages.store');
        Route::put('/messages/{chatMessage}', [ChatController::class, 'update'])->name('messages.update');
        Route::delete('/messages/{chatMessage}', [ChatController::class, 'destroy'])->name('messages.destroy');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
    Route::middleware(['role:developer,admin', 'permission:view_activity_logs'])
        ->get('/sessions', [UserSessionController::class, 'index'])
        ->name('sessions.index');

    Route::middleware('role:developer')->prefix('developers')->name('developers.')->group(function () {
        Route::middleware('permission:manage_admins')->group(function () {
            Route::resource('administrators', AdministratorController::class)->except('show');
            Route::get('/users/{user}/permissions', [UserPermissionController::class, 'edit'])->name('permissions.edit');
            Route::put('/users/{user}/permissions', [UserPermissionController::class, 'update'])->name('permissions.update');
        });
        Route::get('/health', [HealthCheckController::class, 'index'])->name('health.index');
        Route::post('/health/run-backup', [HealthCheckController::class, 'runBackup'])->name('health.run_backup');
        Route::get('/security/password', [DeveloperSecurityController::class, 'editPassword'])->name('security.password.edit');
        Route::put('/security/password', [DeveloperSecurityController::class, 'updatePassword'])->name('security.password.update');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/security/password', [AdminSecurityController::class, 'editPassword'])->name('security.password.edit');
        Route::put('/security/password', [AdminSecurityController::class, 'updatePassword'])->name('security.password.update');
        Route::middleware('permission:manage_data_entries')->group(function () {
            Route::resource('data-entry-users', DataEntryUserController::class)->except('show');
        });
    });

        Route::middleware('role:developer,admin,data_entry')->group(function () {
            Route::middleware('permission:manage_families')->group(function () {
                Route::resource('families', FamilyController::class)->except('show');
                Route::get('/orphans', [FamilyController::class, 'orphansIndex'])->name('orphans.index');
                Route::get('/orphans/create', [FamilyController::class, 'createOrphan'])->name('orphans.create');
                Route::post('/orphans', [FamilyController::class, 'storeOrphan'])->name('orphans.store');
                Route::get('/orphans/{orphan}/edit', [FamilyController::class, 'editOrphan'])->name('orphans.edit');
                Route::put('/orphans/{orphan}', [FamilyController::class, 'updateOrphan'])->name('orphans.update');
                Route::delete('/orphans/{orphan}', [FamilyController::class, 'destroyOrphan'])->name('orphans.destroy');
                Route::get('/family-members/search', [FamilyController::class, 'membersSearch'])->name('families.members.search');
            Route::post('/families/{family}/attachments', [FamilyAttachmentController::class, 'store'])->name('families.attachments.store');
            Route::get('/families/{family}/attachments/{attachment}', [FamilyAttachmentController::class, 'download'])->name('families.attachments.download');
            Route::delete('/families/{family}/attachments/{attachment}', [FamilyAttachmentController::class, 'destroy'])->name('families.attachments.destroy');
            Route::view('/offline-queue', 'offline_queue.index')->name('offline_queue.index');
        });
    });
    Route::middleware(['role:developer,admin', 'permission:export_reports'])
        ->get('/families-export', [FamilyController::class, 'export'])
        ->name('families.export');
    Route::middleware(['role:developer,admin', 'permission:export_reports'])
        ->get('/families-special-export', [FamilyController::class, 'exportSpecial'])
        ->name('families.special_export');

    Route::middleware('role:developer,admin')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/print', [ReportController::class, 'printReport'])->name('print');
        Route::middleware('permission:export_reports')->group(function () {
            Route::get('/families-export', [ReportController::class, 'exportFamilies'])->name('families.export');
            Route::get('/orphans-export', [ReportController::class, 'exportOrphans'])->name('orphans.export');
            Route::get('/statistics-export', [ReportController::class, 'exportStatistics'])->name('statistics.export');
            Route::get('/widows-export', [ReportController::class, 'exportWidowsByStatus'])->name('widows.export');
        });
        Route::middleware('permission:import_families')->post('/families-import', [FamilyImportController::class, 'import'])->name('families.import');
    });

    Route::middleware('role:developer,admin')->prefix('benefits')->name('benefits.')->group(function () {
        Route::get('/', [BenefitController::class, 'index'])->name('index');
        Route::post('/', [BenefitController::class, 'store'])->name('store');
        Route::put('/{benefit}', [BenefitController::class, 'update'])->name('update');
        Route::delete('/{benefit}', [BenefitController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['role:developer,admin', 'permission:view_activity_logs'])
        ->get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity_logs.index');
});

