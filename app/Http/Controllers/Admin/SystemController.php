<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\GameConfig;
use App\Support\SettingsSchema;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Pengaturan game, akun admin, TOTP, dan log audit. */
class SystemController extends Controller
{
    public function settings(GameConfig $game)
    {
        $overrides = Setting::pluck('value', 'key')->all();

        return view('admin.settings', [
            'groups' => SettingsSchema::groups(),
            'current' => fn ($k) => $game->get($k),
            'default' => fn ($k) => config('game.'.$k),
            'overridden' => $overrides,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $errors = [];
        $changed = [];
        foreach (SettingsSchema::all() as $key => [$k, $label, $type]) {
            $field = str_replace('.', '__', $key);
            if ($request->boolean("reset__{$field}")) {
                Setting::where('key', $key)->delete();
                $changed[$key] = 'reset';

                continue;
            }
            if ($type !== 'bool' && ! $request->has($field)) {
                continue;
            }
            $value = SettingsSchema::cast($type, $type === 'bool' ? $request->boolean($field) : $request->input($field));
            if ($value === null) {
                $errors[$field] = "{$label}: nilai tidak valid.";

                continue;
            }
            if ($value !== config('game.'.$key) || Setting::whereKey($key)->exists()) {
                $current = Setting::find($key)?->value;
                if ($value === config('game.'.$key)) {
                    Setting::where('key', $key)->delete();
                } elseif ($current !== $value) {
                    Setting::put($key, $value);
                } else {
                    continue;
                }
                $changed[$key] = $value;
            }
        }
        app(GameConfig::class)->flush();
        if ($changed) {
            AuditLog::record('settings_changed', null, $changed, 'admin', Auth::guard('admin')->id());
        }

        return back()->withErrors($errors)->with('ok', $changed ? count($changed).' pengaturan diperbarui.' : 'Tidak ada perubahan.');
    }

    public function admins()
    {
        return view('admin.admins', ['admins' => Admin::orderBy('id')->get()]);
    }

    public function createAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:190', 'unique:admins,email'],
            'password' => ['required', Password::min(10)],
            'role' => ['required', 'in:owner,moderator,editor'],
        ]);
        $a = Admin::create(['email' => strtolower($data['email'])] + $data);
        AuditLog::record('admin_created', $a, ['role' => $a->role], 'admin', Auth::guard('admin')->id());

        return back()->with('ok', "Admin {$a->email} dibuat.");
    }

    public function deleteAdmin(Admin $admin)
    {
        $me = Auth::guard('admin')->user();
        if ($admin->is($me)) {
            return back()->withErrors(['admin' => 'Tidak bisa menghapus akun sendiri.']);
        }
        if ($admin->role === 'owner' && Admin::where('role', 'owner')->count() <= 1) {
            return back()->withErrors(['admin' => 'Minimal harus ada satu owner.']);
        }
        AuditLog::record('admin_deleted', $admin, ['email' => $admin->email], 'admin', $me->id);
        $admin->delete();

        return back()->with('ok', 'Admin dihapus.');
    }

    public function account(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $pending = $request->session()->get('totp_pending');
        if (! $admin->totp_secret && ! $pending) {
            $pending = Totp::secret();
            $request->session()->put('totp_pending', $pending);
        }

        return view('admin.account', [
            'admin' => $admin,
            'pending' => $admin->totp_secret ? null : $pending,
            'uri' => $pending ? Totp::uri($pending, $admin->email, (string) app(GameConfig::class)->get('brand.name')) : null,
        ]);
    }

    public function saveAccount(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $act = $request->input('action');

        if ($act === 'password') {
            $data = $request->validate([
                'current' => ['required', 'current_password:admin'],
                'password' => ['required', 'confirmed', Password::min(10)],
            ]);
            $admin->forceFill(['password' => $data['password']])->save();
            AuditLog::record('admin_password_changed', $admin, [], 'admin', $admin->id);

            return back()->with('ok', 'Password diganti.');
        }
        if ($act === 'totp_on') {
            $secret = (string) $request->session()->get('totp_pending');
            if (! $secret || ! Totp::verify($secret, (string) $request->input('otp'))) {
                return back()->withErrors(['otp' => 'Kode tidak cocok. Pastikan jam HP benar, lalu coba lagi.']);
            }
            $admin->forceFill(['totp_secret' => $secret])->save();
            $request->session()->forget('totp_pending');
            AuditLog::record('admin_totp_on', $admin, [], 'admin', $admin->id);

            return back()->with('ok', 'Autentikator aktif. Login berikutnya butuh kode 6 digit.');
        }
        if ($act === 'totp_off') {
            $request->validate(['current' => ['required', 'current_password:admin']]);
            $admin->forceFill(['totp_secret' => null])->save();
            AuditLog::record('admin_totp_off', $admin, [], 'admin', $admin->id);

            return back()->with('ok', 'Autentikator dimatikan.');
        }

        return back();
    }

    public function audit(Request $request)
    {
        $q = AuditLog::query();
        if ($a = $request->query('action')) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $a).'%');
        }

        return view('admin.audit', [
            'logs' => $q->latest('id')->paginate(100)->withQueryString(),
            'admins' => Admin::pluck('email', 'id'),
        ]);
    }
}
