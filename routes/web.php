<?php

use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\FighterController;
use Illuminate\Support\Facades\Route;

/*
| API berbasis sesi cookie ada di bawah prefix /api (masih di grup "web",
| jadi dapat sesi dan CSRF). Daftar lengkap rencananya ada di blueprint,
| bagian "Kontrak API". Fase berikutnya menambah auth, arena, ranking, dst.
*/
Route::prefix('api')->middleware('throttle:api-read')->group(function () {
    Route::get('engine/preview/{a}/{b}', [EngineController::class, 'preview']);
    Route::get('fighters/{username}', [FighterController::class, 'show']);
});

/*
| Halaman: satu shell SPA. Router JavaScript (resources/js/router.js) yang
| memilih tampilan berdasarkan path. /{username} = profil petarung.
*/
$shell = fn () => view('app');

Route::get('/', $shell)->name('home');
Route::get('/{page}', $shell)->where('page', 'mading|latihan|markas|piala|profil|dukung|privasi')->name('page');
Route::get('/t/{id}', $shell)->where('id', '[A-Za-z0-9_-]{6,16}')->name('challenge');
Route::get('/{username}', $shell)
    ->where('username', '(?!up$|api$|build$|admin$)[A-Za-z0-9._]{1,30}')
    ->name('fighter');
