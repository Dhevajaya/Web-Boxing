<?php

namespace App\Http\Middleware;

use App\Services\Auth\PlayerAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Mengeluarkan pemain yang sesinya sudah dicabut atau akunnya disuspend. */
class ValidateFighterSession
{
    public function __construct(private readonly PlayerAuth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('fighter');
        if ($guard->check() && ! $this->auth->stillValid($request, $guard->user())) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
