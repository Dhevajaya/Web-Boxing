<?php

namespace App\Providers;

use App\Game\Looks;
use App\Game\NameRules;
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

        // Katalog tampilan avatar (resources/game/looks.json), juga dipakai JavaScript.
        $this->app->singleton(Looks::class, fn () => Looks::load(resource_path('game')));
        $this->app->bind(NameRules::class, fn ($app) => new NameRules($app->make(GameConfig::class)->get('profile')));
    }

    public function boot(): void
    {
        // Operator seluler sering memakai CGNAT (banyak pemain berbagi satu IP), jadi batas utama
        // per sesi browser; batas per IP hanya rem kasar untuk bot.
        RateLimiter::for('api-read', fn (Request $r) => [
            Limit::perMinute(240)->by('read-s:'.($r->session()?->getId() ?: $r->ip())),
            Limit::perMinute(3000)->by('read-ip:'.$r->ip()),
        ]);
        RateLimiter::for('chat', fn (Request $r) => Limit::perMinute(20)->by('chat:'.($r->session()?->getId() ?: $r->ip())));
        RateLimiter::for('api-write', fn (Request $r) => Limit::perMinute(30)->by($r->session()?->getId() ?: $r->ip()));
        // Minta kode / mulai login: 10 per 10 menit per IP, 5 per 10 menit per username.
        RateLimiter::for('auth', function (Request $r) {
            $limits = [Limit::perMinutes(10, 10)->by('auth-ip:'.$r->ip())];
            if ($u = strtolower(trim((string) $r->input('username'), " @\t\n"))) {
                $limits[] = Limit::perMinutes(10, 5)->by('auth-u:'.$u);
            }

            return $limits;
        });
    }
}
