<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\FighterController;
use App\Http\Controllers\Api\InstagramWebhookController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Middleware\RequireFighter;
use Illuminate\Support\Facades\Route;

/*
| API berbasis sesi cookie ada di bawah prefix /api (masih di grup "web",
| jadi dapat sesi dan CSRF). Daftar lengkap rencananya ada di blueprint,
| bagian "Kontrak API".
*/
Route::prefix('api')->group(function () {
    Route::middleware('throttle:api-read')->group(function () {
        Route::get('engine/preview/{a}/{b}', [EngineController::class, 'preview']);
        Route::get('fighters/{username}', [FighterController::class, 'show']);

        Route::get('auth/options', [AuthController::class, 'options']);
        Route::get('auth/status/{id}', [AuthController::class, 'status'])->whereUuid('id');
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/ref/start', [AuthController::class, 'startRef']);
        Route::post('auth/code/start', [AuthController::class, 'startCode']);
        Route::post('auth/dev', [AuthController::class, 'dev']);
    });

    Route::post('me/logout', [AuthController::class, 'logout'])->middleware(RequireFighter::class);

    // Profil milik pemain yang masuk (Fase 3).
    Route::middleware(RequireFighter::class)->group(function () {
        Route::get('me/profile', [ProfileController::class, 'show'])->middleware('throttle:api-read');
        Route::middleware('throttle:api-write')->group(function () {
            Route::put('me/look', [ProfileController::class, 'updateLook']);
            Route::put('me/identity', [ProfileController::class, 'updateIdentity']);
            Route::put('me/points', [ProfileController::class, 'updatePoints']);
        });
    });

    // Webhook dikecualikan dari CSRF di bootstrap/app.php; keasliannya dicek lewat signature.
    Route::get('webhooks/instagram', [InstagramWebhookController::class, 'verify']);
    Route::post('webhooks/instagram', [InstagramWebhookController::class, 'receive'])->middleware('throttle:600,1');
});

Route::get('/masuk/{token}', [AuthController::class, 'consume'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:auth')
    ->name('login.link');

/*
| Halaman: satu shell SPA. Router JavaScript (resources/js/router.js) yang
| memilih tampilan berdasarkan path. /{username} = profil petarung.
*/
$shell = fn () => view('app');

Route::get('/', $shell)->name('home');
Route::get('/{page}', $shell)->where('page', 'mading|latihan|markas|piala|profil|dukung|privasi')->name('page');
Route::get('/profil/{section}', $shell)->where('section', 'tampilan|identitas|stat')->name('profile.edit');
Route::get('/t/{id}', $shell)->where('id', '[A-Za-z0-9_-]{6,16}')->name('challenge');
Route::get('/{username}', fn (string $username) => view('app', ['pageTitle' => '@'.strtolower($username)]))
    ->where('username', '(?!up$|api$|build$|admin$|masuk$)[A-Za-z0-9._]{1,30}')
    ->name('fighter');
