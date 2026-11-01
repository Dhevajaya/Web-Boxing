<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cup;
use App\Models\FightNightRun;
use App\Models\LoginRequest;
use App\Models\Season;
use App\Services\Auth\LoginService;
use App\Services\Game\FightNight;
use App\Services\Game\Piala;
use App\Services\Game\Seasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Login manual, Fight Night, season, piala. */
class OpsController extends Controller
{
    public function logins()
    {
        return view('admin.logins', [
            'pending' => LoginRequest::where('method', 'code')->where('status', 'pending')->where('expires_at', '>', now())->latest()->limit(50)->get(),
            'recent' => LoginRequest::whereIn('status', ['ok', 'used', 'rejected'])->latest('updated_at')->limit(20)->get(),
        ]);
    }

    public function approve(Request $request, LoginService $login)
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
        $lr = $login->approveManually($code);
        if (! $lr) {
            return back()->withErrors(['code' => 'Kode tidak ditemukan, sudah dipakai, atau kedaluwarsa.']);
        }
        AuditLog::record('admin_login_approved', $lr, ['username' => $lr->requested_username], 'admin', Auth::guard('admin')->id());

        return back()->with('ok', "Disetujui: @{$lr->requested_username}. Browser pemain masuk otomatis.");
    }

    public function reject(LoginRequest $loginRequest)
    {
        if ($loginRequest->status === 'pending') {
            $loginRequest->forceFill(['status' => 'rejected'])->save();
            AuditLog::record('admin_login_rejected', $loginRequest, ['username' => $loginRequest->requested_username], 'admin', Auth::guard('admin')->id());
        }

        return back()->with('ok', 'Permintaan ditolak.');
    }

    public function operations(Piala $piala)
    {
        return view('admin.operations', [
            'runs' => FightNightRun::orderByDesc('slot_at')->limit(30)->get(),
            'season' => Season::current(),
            'seasons' => Season::orderByDesc('number')->limit(10)->get(),
            'cup' => $piala->current(),
            'cups' => Cup::orderByDesc('week_start')->limit(8)->get(),
        ]);
    }

    public function retryRun(FightNightRun $run, FightNight $fn)
    {
        $fn->retry($run);
        AuditLog::record('fightnight_retry', $run, [], 'admin', Auth::guard('admin')->id());

        return back()->with('ok', 'Run dijadwalkan ulang; cron berikutnya melanjutkan.');
    }

    public function closeSeason(Request $request, Seasons $seasons)
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:64'], 'confirm' => ['required', 'in:TUTUP']], [
            'confirm.in' => 'Ketik TUTUP untuk konfirmasi.',
        ]);
        $r = $seasons->close($data['name'] ?? null, Auth::guard('admin')->id());

        return back()->with('ok', "{$r['closed']->name} ditutup ({$r['ranked']} berperingkat). {$r['next']->name} dimulai.");
    }

    public function cancelCup(Cup $cup, Piala $piala)
    {
        $piala->cancel($cup);
        AuditLog::record('cup_cancelled', $cup, [], 'admin', Auth::guard('admin')->id());

        return back()->with('ok', 'Piala dibatalkan.');
    }
}
