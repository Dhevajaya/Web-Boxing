<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Halaman admin: wajib masuk sebagai admin, opsional peran minimal (admin.auth:owner). */
class AdminAuth
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return redirect()->guest(route('admin.login'));
        }
        if ($role && ! $admin->hasRole($role)) {
            abort(403, 'Peranmu tidak punya akses ke halaman ini.');
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
