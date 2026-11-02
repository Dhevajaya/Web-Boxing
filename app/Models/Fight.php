<?php

namespace App\Models;

use App\Game\FightSimulator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fight extends Model
{
    public const STATS = ['power', 'speed', 'stamina', 'defense', 'chin'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'slot_at' => 'datetime',
            'visible_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function fighterA(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'a_id');
    }

    public function fighterB(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'b_id');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('visible_at', '<=', now());
    }

    /** Kolom lama (5 stat tinju) diisi dari atribut v2 agar kueri lama tetap bekerja. */
    public const V2_LEGACY = ['power' => 'strength', 'speed' => 'speed', 'stamina' => 'stamina', 'defense' => 'defense', 'chin' => 'endurance'];

    /** @return array{style:string, stats:array<string,int>, jurus?:list<string>} */
    public function input(string $side): array
    {
        $snap = $this->{"{$side}_snap"};
        if ($snap) {
            return is_array($snap) ? $snap : json_decode($snap, true);
        }
        $stats = [];
        foreach (self::STATS as $k) {
            $stats[$k] = (int) $this->{"{$side}_{$k}"};
        }

        return ['style' => $this->{"{$side}_style"}, 'stats' => $stats];
    }

    /**
     * Kolom yang disimpan dari input simulasi (dan ringkasan hasil bila $r diberikan).
     * Nilai JSON sudah di-encode agar bisa dipakai Fight::create() maupun insert massal.
     */
    public static function statColumns(array $a, array $b, ?array $r = null): array
    {
        $cols = ['a_style' => $a['style'], 'b_style' => $b['style']];
        $v2 = ! isset($a['stats']['power']);
        foreach (self::STATS as $k) {
            $src = $v2 ? self::V2_LEGACY[$k] : $k;
            $cols["a_{$k}"] = $a['stats'][$src];
            $cols["b_{$k}"] = $b['stats'][$src];
        }
        if ($v2) {
            $cols['a_snap'] = json_encode($a);
            $cols['b_snap'] = json_encode($b);
        }
        if ($r && ($r['version'] ?? 1) >= 2) {
            $cols['summary'] = json_encode(self::summarize($r));
        }

        return $cols;
    }

    /** Ringkasan kecil hasil v2: skor per ronde, serangan bersih, dan aksi penting. */
    public static function summarize(array $r): array
    {
        $key = [];
        foreach ($r['events'] as $e) {
            if (in_array($e['t'], ['kd', 'ko', 'jurus', 'leg'], true)) {
                $key[] = array_filter(['t' => $e['t'], 'rd' => $e['rd'], 'who' => $e['who'] ?? null, 'j' => $e['j'] ?? null, 'p' => $e['p'] ?? null, 'why' => $e['why'] ?? null]);
            }
        }

        return ['rounds' => $r['rounds'] ?? [], 'clean' => $r['clean'] ?? null, 'key' => array_slice($key, 0, 30)];
    }

    public function summaryData(): ?array
    {
        $s = $this->summary;

        return $s ? (is_array($s) ? $s : json_decode($s, true)) : null;
    }

    /** Putar ulang laga (hasil identik dengan saat laga dibuat). */
    public function replay(): array
    {
        return app(FightSimulator::class)->simulate($this->input('a'), $this->input('b'), (int) $this->seed, (int) $this->engine_version);
    }

    /** Data yang dikirim ke browser untuk diputar ulang oleh resources/js/game. */
    public function toReplayPayload(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'v' => (int) $this->engine_version,
            'seed' => (int) $this->seed,
            'a' => $this->input('a'),
            'b' => $this->input('b'),
            'winner' => $this->winner,
            'how' => $this->how,
            'round' => (int) $this->round,
            'summary' => $this->summaryData(),
            'at' => $this->visible_at?->toIso8601String(),
        ];
    }
}
