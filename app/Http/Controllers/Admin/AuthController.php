<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function show()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'otp' => ['nullable', 'string', 'max:12'],
        ]);

        $key = 'admin-login:'.$request->ip().'|'.strtolower($data['email']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput(['email' => $data['email']])
                ->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        $admin = Admin::where('email', strtolower($data['email']))->first();
        $ok = $admin && Hash::check($data['password'], $admin->password);
        if ($ok && $admin->totp_secret) {
            $ok = Totp::verify($admin->totp_secret, (string) ($data['otp'] ?? ''));
        }
        if (! $ok) {
            RateLimiter::hit($key, 300);

            return back()->withInput(['email' => $data['email']])
                ->withErrors(['email' => $admin?->totp_secret && Hash::check($data['password'], $admin->password)
                    ? 'Kode autentikator salah.' : 'Email atau password salah.']);
        }

        RateLimiter::clear($key);
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('admin_login', $admin, [], 'admin', $admin->id);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
