<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Game\Supporters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    public function index()
    {
        return view('admin.support', [
            'donations' => DB::table('donations')->leftJoin('fighters', 'fighters.id', '=', 'donations.fighter_id')
                ->select('donations.*', 'fighters.instagram_username as matched')->orderByDesc('donations.id')->paginate(50),
            'total' => (int) DB::table('donations')->sum('amount'),
        ]);
    }

    /** Catat donasi manual (transfer langsung, dsb.). */
    public function store(Request $request, Supporters $supporters)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:31'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $f = $supporters->record('manual', 'm'.now()->format('YmdHisv'), (int) $data['amount'], null, '@'.ltrim($data['username'], '@').' '.($data['note'] ?? ''));
        if (! $f) {
            return back()->withErrors(['username' => 'Petarung tidak ditemukan (donasi tetap tercatat tanpa pemilik).']);
        }
        AuditLog::record('donation_manual', $f, ['amount' => (int) $data['amount']], 'admin', Auth::guard('admin')->id());

        return back()->with('ok', "Donasi @{$f->instagram_username} dicatat. Tier sekarang: ".($f->refresh()->supporter_tier ?? '-').'.');
    }
}
