<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Endpoint yang butuh pemain masuk: 401 JSON bila belum. */
class RequireFighter
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('fighter')->check()) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Masuk dulu ya.']], 401);
        }

        return $next($request);
    }
}
