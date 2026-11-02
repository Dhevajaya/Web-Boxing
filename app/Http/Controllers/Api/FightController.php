<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fight;
use App\Models\Fighter;
use Illuminate\Http\Request;

/** Laga yang sudah terlihat: untuk diputar ulang di arena dan riwayat di kartu petarung. */
class FightController extends Controller
{
    public function show(string $id)
    {
        $f = Fight::visible()->with(['fighterA', 'fighterB'])->find((int) $id);
        if (! $f) {
            return $this->fail('not_found', 'Laga tidak ditemukan atau belum dimulai.', 404);
        }

        return $this->ok(self::payload($f), headers: ['Cache-Control' => 'public, max-age=60']);
    }

    /** Riwayat laga seorang petarung, terbaru dulu. ?before={id} untuk halaman berikutnya. */
    public function forFighter(Request $request, string $username)
    {
        $f = Fighter::findByUsername($username);
        if (! $f) {
            return $this->ok(['items' => [], 'next' => null]);
        }

        $limit = 15;
        $q = Fight::visible()
            ->where(fn ($q) => $q->where('a_id', $f->id)->orWhere('b_id', $f->id))
            ->with(['fighterA:id,instagram_username,nick,kota,look,xp,is_npc,supporter_tier,supporter_anonymous', 'fighterB:id,instagram_username,nick,kota,look,xp,is_npc,supporter_tier,supporter_anonymous'])
            ->orderByDesc('visible_at')->orderByDesc('id');
        if ($before = $request->integer('before')) {
            $q->where('id', '<', $before);
        }
        $rows = $q->limit($limit + 1)->get();

        $items = $rows->take($limit)->map(function (Fight $x) use ($f) {
            $side = $x->a_id === $f->id ? 'a' : 'b';
            $opp = $side === 'a' ? $x->fighterB : $x->fighterA;

            return [
                'id' => $x->id,
                'kind' => $x->kind,
                'at' => $x->visible_at->toIso8601String(),
                'result' => $x->winner === 'draw' ? 'draw' : ($x->winner === $side ? 'win' : 'lose'),
                'how' => $x->how,
                'round' => (int) $x->round,
                'xp' => (int) $x->{"xp_{$side}"},
                'code' => $x->code ?? null,
                'v' => (int) $x->engine_version,
                'opponent' => $opp?->summary(),
            ];
        })->values();

        return $this->ok([
            'items' => $items,
            'next' => $rows->count() > $limit ? $items->last()['id'] : null,
        ], headers: ['Cache-Control' => 'public, max-age=30']);
    }

    public static function payload(Fight $f): array
    {
        $f->loadMissing(['fighterA', 'fighterB']);

        return $f->toReplayPayload() + [
            'scoreA' => (int) $f->score_a,
            'scoreB' => (int) $f->score_b,
            'xp' => ['a' => (int) $f->xp_a, 'b' => (int) $f->xp_b],
            'code' => $f->code ?? null,
            'fighters' => ['a' => $f->fighterA?->summary(), 'b' => $f->fighterB?->summary()],
        ];
    }
}
