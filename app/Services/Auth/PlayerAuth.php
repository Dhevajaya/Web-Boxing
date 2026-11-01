<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Fighter;
use App\Models\PlayerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Membuat dan mengakhiri sesi pemain di browser.
 * Setiap sesi dicatat di player_sessions (ID sesi di-hash) agar bisa
 * dicabut, misalnya "keluar dari semua perangkat".
 */
class PlayerAuth
{
    public const KEY = 'player_session_id';

    public function login(Request $request, Fighter $fighter, string $method): void
    {
        Auth::guard('fighter')->login($fighter);
        $request->session()->regenerate();

        $row = PlayerSession::create([
            'fighter_id' => $fighter->id,
            'session_id_hash' => PlayerSession::hashId($request->session()->getId()),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'last_seen_at' => now(),
        ]);
        // ID baris disimpan di data sesi (sisi server), dipakai untuk mencabut sesi ini.
        $request->session()->put(self::KEY, $row->id);

        $fighter->forceFill(['last_active_at' => now()])->save();
        AuditLog::record('login', $fighter, ['method' => $method], 'fighter', $fighter->id);
    }

    public function logout(Request $request, bool $everywhere = false): void
    {
        $fighter = Auth::guard('fighter')->user();
        if ($fighter) {
            $q = PlayerSession::where('fighter_id', $fighter->id)->whereNull('revoked_at');
            if (! $everywhere) {
                $q->whereKey($request->session()->get(self::KEY));
            }
            $q->update(['revoked_at' => now()]);
            AuditLog::record($everywhere ? 'logout_all' : 'logout', $fighter, [], 'fighter', $fighter->id);
        }

        Auth::guard('fighter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** Dipanggil middleware: sesi masih sah? (tidak dicabut, petarung masih aktif) */
    public function stillValid(Request $request, Fighter $fighter): bool
    {
        if ($fighter->status !== 'active') {
            return false;
        }

        $id = $request->session()->get(self::KEY);
        $row = $id ? PlayerSession::whereKey($id)->where('fighter_id', $fighter->id)->first() : null;

        if (! $row || $row->revoked_at) {
            return false;
        }

        // Catat aktivitas paling sering tiap 10 menit agar tidak menulis ke DB di setiap request.
        if (! $row->last_seen_at || $row->last_seen_at->lt(now()->subMinutes(10))) {
            $row->forceFill(['last_seen_at' => now()])->save();
            $fighter->forceFill(['last_active_at' => now()])->save();
        }

        return true;
    }
}
