<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fighter;
use App\Services\Game\Supporters;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    /** Halaman Dukung: tier, tautan donasi, daftar pendukung (yang tidak anonim). */
    public function index(GameConfig $game)
    {
        $people = Fighter::players()->whereNotNull('supporter_tier')->where('supporter_anonymous', false)->where('status', 'active')
            ->orderByRaw("CASE supporter_tier WHEN 'vip' THEN 3 WHEN 'ringside' THEN 2 ELSE 1 END DESC")->orderBy('id')->limit(100)->get();
        /** @var Fighter|null $me */
        $me = Auth::guard('fighter')->user();

        return $this->ok([
            'url' => $game->get('supporter.saweria_url'),
            'tiers' => $game->get('supporter.tiers'),
            'supporters' => $people->map(fn ($f) => $f->summary() + ['tier' => $f->supporter_tier])->values(),
            'me' => $me ? [
                'tier' => $me->supporter_tier,
                'anonymous' => (bool) $me->supporter_anonymous,
                'total' => (int) DB::table('donations')->where('fighter_id', $me->id)->sum('amount'),
            ] : null,
        ])->header('Cache-Control', 'private, max-age=30');
    }

    /** PUT /api/me/supporter { anonymous: bool } */
    public function update(Request $request)
    {
        $me = Auth::guard('fighter')->user();
        $me->forceFill(['supporter_anonymous' => $request->boolean('anonymous')])->save();

        return $this->ok(['anonymous' => (bool) $me->supporter_anonymous]);
    }

    /**
     * POST /api/webhooks/saweria/{token}
     * Saweria mengirim JSON donasi (id, amount_raw, donator_name, message, ...).
     * Keaslian dicek lewat token rahasia di URL (SUPPORT_WEBHOOK_TOKEN).
     */
    public function saweria(Request $request, string $token, GameConfig $game, Supporters $supporters)
    {
        $secret = (string) $game->get('supporter.webhook_token');
        if ($secret === '' || ! hash_equals($secret, $token)) {
            return $this->fail('forbidden', 'Token salah.', 403);
        }
        $p = $request->json()->all();
        $id = (string) ($p['id'] ?? $p['transaction_id'] ?? '');
        $amount = (int) ($p['amount_raw'] ?? $p['amount'] ?? 0);
        if ($id === '' || $amount <= 0) {
            return $this->fail('invalid', 'Data donasi tidak lengkap.');
        }
        $f = $supporters->record('saweria', $id, $amount, $p['donator_name'] ?? null, $p['message'] ?? null, $p);

        return $this->ok(['matched' => $f?->instagram_username]);
    }
}
