<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\PlayerSession;
use App\Services\Game\ResultApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FighterController extends Controller
{
    public function index(Request $request)
    {
        $q = Fighter::query()->with('sasana:id,icon');
        if ($s = trim((string) $request->query('q'))) {
            $q->where('instagram_username', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], strtolower(ltrim($s, '@'))).'%');
        }
        if ($request->query('filter') === 'suspended') {
            $q->where('status', 'suspended');
        } elseif ($request->query('filter') === 'npc') {
            $q->where('is_npc', true);
        } elseif ($request->query('filter') === 'muted') {
            $q->where('muted_until', '>', now());
        } else {
            $q->where('is_npc', false);
        }

        return view('admin.fighters', [
            'fighters' => $q->orderByDesc('last_active_at')->paginate(50)->withQueryString(),
        ]);
    }

    public function show(Fighter $fighter)
    {
        return view('admin.fighter', [
            'f' => $fighter,
            'fights' => Fight::where(fn ($q) => $q->where('a_id', $fighter->id)->orWhere('b_id', $fighter->id))
                ->with(['fighterA:id,instagram_username', 'fighterB:id,instagram_username'])->latest('id')->limit(20)->get(),
            'logs' => AuditLog::where('subject_type', 'Fighter')->where('subject_id', (string) $fighter->id)->latest('id')->limit(30)->get(),
            'sessions' => PlayerSession::where('fighter_id', $fighter->id)->whereNull('revoked_at')->count(),
            'badges' => Badge::where('fighter_id', $fighter->id)->get(),
            'xpLog' => DB::table('xp_ledger')->where('fighter_id', $fighter->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function action(Request $request, Fighter $fighter, ResultApplier $applier)
    {
        $admin = Auth::guard('admin')->user();
        $act = (string) $request->input('action');
        $note = mb_substr(trim((string) $request->input('note')), 0, 200);
        $msg = 'Tersimpan.';

        switch ($act) {
            case 'suspend':
                $fighter->forceFill(['status' => 'suspended'])->save();
                PlayerSession::where('fighter_id', $fighter->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $msg = 'Petarung disuspend dan semua sesinya dicabut.';
                break;
            case 'unsuspend':
                $fighter->forceFill(['status' => 'active'])->save();
                $msg = 'Suspend dicabut.';
                break;
            case 'mute':
                $hours = max(1, min(24 * 30, (int) $request->input('hours', 24)));
                $fighter->forceFill(['muted_until' => now()->addHours($hours)])->save();
                $msg = "Dibisukan {$hours} jam.";
                break;
            case 'unmute':
                $fighter->forceFill(['muted_until' => null])->save();
                $msg = 'Bisu dicabut.';
                break;
            case 'reset_identity':
                $fighter->forceFill(['nick' => null, 'kota' => null])->save();
                $msg = 'Julukan dan kota kembali ke bawaan.';
                break;
            case 'reset_look':
                $fighter->forceFill(['look' => null])->save();
                $msg = 'Tampilan kembali ke bawaan.';
                break;
            case 'revoke_sessions':
                PlayerSession::where('fighter_id', $fighter->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $msg = 'Semua sesi dicabut.';
                break;
            case 'xp':
                $amount = (int) $request->input('amount');
                if ($amount === 0 || abs($amount) > 10000) {
                    return back()->withErrors(['amount' => 'Jumlah XP tidak valid (±1..10000).']);
                }
                if ($amount > 0) {
                    $applier->grant($fighter->id, 'admin', 'a'.$admin->id.'-'.now()->format('YmdHisv'), $amount);
                } else {
                    DB::table('xp_ledger')->insert(['fighter_id' => $fighter->id, 'source' => 'admin', 'source_id' => 'a'.$admin->id.'-'.now()->format('YmdHisv'), 'amount' => $amount, 'created_at' => now()]);
                    $xp = max(0, (int) $fighter->xp + $amount);
                    $fighter->forceFill(['xp' => $xp, 'level' => \App\Game\Progression::fromConfig()->level($xp)['lv']])->save();
                }
                $msg = 'XP disesuaikan '.($amount > 0 ? '+' : '').$amount.'.';
                break;
            case 'supporter':
                $tier = $request->input('tier');
                $tier = in_array($tier, ['tribun', 'ringside', 'vip'], true) ? $tier : null;
                $fighter->forceFill(['supporter_tier' => $tier])->save();
                if ($tier) {
                    Badge::award($fighter->id, 'pendukung:'.$tier, ['manual' => true]);
                }
                $msg = $tier ? "Status pendukung: {$tier}." : 'Status pendukung dihapus.';
                break;
            default:
                return back()->withErrors(['action' => 'Aksi tidak dikenal.']);
        }

        AuditLog::record('admin_fighter_'.$act, $fighter, array_filter(['note' => $note, 'amount' => $request->input('amount'), 'hours' => $request->input('hours'), 'tier' => $request->input('tier')]), 'admin', $admin->id);

        return back()->with('ok', $msg);
    }
}
