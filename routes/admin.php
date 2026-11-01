<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FighterController;
use App\Http\Controllers\Admin\OpsController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

/*
| Panel admin (server-rendered, Blade). Peran: owner > moderator > editor.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(AdminAuth::class)->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('akun', [SystemController::class, 'account'])->name('account');
        Route::post('akun', [SystemController::class, 'saveAccount']);

        Route::middleware(AdminAuth::class.':editor')->group(function () {
            Route::get('pengumuman', [ContentController::class, 'announcements'])->name('announcements');
            Route::get('pengumuman/baru', [ContentController::class, 'editAnnouncement'])->name('announcements.new');
            Route::post('pengumuman/baru', [ContentController::class, 'saveAnnouncement']);
            Route::get('pengumuman/{announcement}', [ContentController::class, 'editAnnouncement'])->name('announcements.edit');
            Route::post('pengumuman/{announcement}', [ContentController::class, 'saveAnnouncement']);
            Route::post('pengumuman/{announcement}/hapus', [ContentController::class, 'deleteAnnouncement'])->name('announcements.delete');
        });

        Route::middleware(AdminAuth::class.':moderator')->group(function () {
            Route::get('petarung', [FighterController::class, 'index'])->name('fighters');
            Route::get('petarung/{fighter}', [FighterController::class, 'show'])->name('fighters.show');
            Route::post('petarung/{fighter}', [FighterController::class, 'action']);
            Route::get('chat', [ContentController::class, 'chat'])->name('chat');
            Route::post('chat/{message}', [ContentController::class, 'chatAction'])->name('chat.action');
            Route::get('login-manual', [OpsController::class, 'logins'])->name('logins');
            Route::post('login-manual', [OpsController::class, 'approve']);
            Route::post('login-manual/{loginRequest}/tolak', [OpsController::class, 'reject'])->name('logins.reject');
        });

        Route::middleware(AdminAuth::class.':owner')->group(function () {
            Route::get('operasi', [OpsController::class, 'operations'])->name('operations');
            Route::post('operasi/fight-night/{run}/ulang', [OpsController::class, 'retryRun'])->name('runs.retry');
            Route::post('operasi/season/tutup', [OpsController::class, 'closeSeason'])->name('season.close');
            Route::post('operasi/piala/{cup}/batal', [OpsController::class, 'cancelCup'])->name('cup.cancel');
            Route::get('pengaturan', [SystemController::class, 'settings'])->name('settings');
            Route::post('pengaturan', [SystemController::class, 'saveSettings']);
            Route::get('admin', [SystemController::class, 'admins'])->name('admins');
            Route::post('admin', [SystemController::class, 'createAdmin']);
            Route::post('admin/{admin}/hapus', [SystemController::class, 'deleteAdmin'])->name('admins.delete');
            Route::get('audit', [SystemController::class, 'audit'])->name('audit');
            Route::get('dukung', [SupportController::class, 'index'])->name('support');
            Route::post('dukung', [SupportController::class, 'store']);
        });
    });
});
