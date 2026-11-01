<?php

namespace App\Http\Middleware;

use App\Support\GameConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Endpoint modul yang bisa dimatikan dari Pengaturan admin (game.features.*). */
class RequireFeature
{
    public function __construct(private readonly GameConfig $game) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->game->feature($feature)) {
            return response()->json(['error' => ['code' => 'feature_off', 'message' => 'Fitur ini sedang dimatikan admin.']], 503);
        }

        return $next($request);
    }
}
