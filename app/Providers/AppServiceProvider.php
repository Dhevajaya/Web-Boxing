<?php

namespace App\Providers;

use App\Game\Rules;
use App\Support\GameConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameConfig::class);

        // Aturan mesin dimuat dari resources/game/rules.v{n}.json (dipakai juga oleh JavaScript).
        $this->app->singleton(Rules::class, fn () => Rules::load(
            resource_path('game'),
            (int) config('game.engine.version', 1),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('api-write', fn (Request $r) => Limit::perMinute(30)->by($r->session()?->getId() ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinutes(10, 5)->by('ip:'.$r->ip()),
            Limit::perMinutes(10, 5)->by('u:'.strtolower((string) $r->input('username'))),
        ]);
    }
}
