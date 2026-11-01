<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SparController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\MadingController;
use App\Http\Controllers\Api\PialaController;
use App\Http\Controllers\Api\SasanaController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\FightController;
use App\Http\Controllers\Api\FighterController;
use App\Http\Controllers\Api\FightNightController;
use App\Http\Controllers\Api\InstagramWebhookController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RankingController;
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
        Route::get('fighters/{username}/fights', [FightController::class, 'forFighter']);
        Route::get('fights/{id}', [FightController::class, 'show'])->whereNumber('id');
        Route::get('ranking', [RankingController::class, 'index']);
        Route::get('fight-night', [FightNightController::class, 'status']);
        Route::get('challenges/{code}', [ChallengeController::class, 'show'])->where('code', '[A-Za-z0-9_-]{6,16}');
        Route::get('mading', [MadingController::class, 'index']);
        Route::get('piala', [PialaController::class, 'show'])->middleware('feature:piala');
        Route::get('sasana', [SasanaController::class, 'index'])->middleware('feature:sasana');
        Route::get('sasana/war', [SasanaController::class, 'war'])->middleware('feature:sasana');
        Route::get('training', [TrainingController::class, 'index'])->middleware('feature:latihan');
        Route::get('dukung', [SupportController::class, 'index'])->middleware('feature:dukung');
        Route::get('chat/{room}', [ChatController::class, 'index'])->where('room', 'alun|sasana:[a-z0-9_-]{1,32}')->middleware('feature:markas');
        Route::get('seasons', [SeasonController::class, 'index']);
        Route::get('seasons/{number}', [SeasonController::class, 'show'])->whereNumber('number');

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
        Route::get('spar', [SparController::class, 'index'])->middleware('throttle:api-read')->middleware('feature:markas');
        Route::middleware('throttle:api-write')->group(function () {
            Route::put('me/look', [ProfileController::class, 'updateLook']);
            Route::put('me/identity', [ProfileController::class, 'updateIdentity']);
            Route::put('me/points', [ProfileController::class, 'updatePoints']);
            Route::post('challenges', [ChallengeController::class, 'store']);
            Route::post('piala/join', [PialaController::class, 'join'])->middleware('feature:piala');
            Route::post('piala/leave', [PialaController::class, 'leave'])->middleware('feature:piala');
            Route::put('me/sasana', [SasanaController::class, 'choose'])->middleware('feature:sasana');
            Route::put('me/supporter', [SupportController::class, 'update'])->middleware('feature:dukung');
            Route::post('training/start', [TrainingController::class, 'start'])->middleware('feature:latihan');
            Route::post('training/finish', [TrainingController::class, 'finish'])->middleware('feature:latihan');
            Route::post('chat/messages/{id}/report', [ChatController::class, 'report'])->whereNumber('id')->middleware('feature:markas');
        });
        // Chat punya batas sendiri (cooldown di controller); polling baca di api-read.
        Route::middleware('throttle:chat')->group(function () {
            Route::post('chat/{room}', [ChatController::class, 'store'])->where('room', 'alun|sasana:[a-z0-9_-]{1,32}')->middleware('feature:markas');
        });
    });

    // Webhook dikecualikan dari CSRF di bootstrap/app.php; keasliannya dicek lewat signature.
    Route::get('webhooks/instagram', [InstagramWebhookController::class, 'verify']);
    Route::post('webhooks/instagram', [InstagramWebhookController::class, 'receive'])->middleware('throttle:600,1');
    Route::post('webhooks/saweria/{token}', [SupportController::class, 'saweria'])->middleware('throttle:120,1');
});

require __DIR__.'/admin.php';

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
Route::get('/{page}', $shell)->where('page', 'mading|latihan|markas|piala|profil|dukung|privasi|ranking')->name('page');
Route::get('/profil/{section}', $shell)->where('section', 'tampilan|identitas|stat|sasana')->name('profile.edit');
Route::get('/laga/{id}', $shell)->whereNumber('id')->name('fight');
Route::get('/t/{id}', $shell)->where('id', '[A-Za-z0-9_-]{6,16}')->name('challenge');
Route::get('/{username}', fn (string $username) => view('app', ['pageTitle' => '@'.strtolower($username)]))
    ->where('username', '(?!up$|api$|build$|admin$|masuk$)[A-Za-z0-9._]{1,30}')
    ->name('fighter');
