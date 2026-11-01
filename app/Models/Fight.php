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

    /** @return array{style:string, stats:array<string,int>} */
    public function input(string $side): array
    {
        $stats = [];
        foreach (self::STATS as $k) {
            $stats[$k] = (int) $this->{"{$side}_{$k}"};
        }

        return ['style' => $this->{"{$side}_style"}, 'stats' => $stats];
    }

    /** Kolom stat yang disimpan dari input simulasi. */
    public static function statColumns(array $a, array $b): array
    {
        $cols = ['a_style' => $a['style'], 'b_style' => $b['style']];
        foreach (self::STATS as $k) {
            $cols["a_{$k}"] = $a['stats'][$k];
            $cols["b_{$k}"] = $b['stats'][$k];
        }

        return $cols;
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
            'at' => $this->visible_at?->toIso8601String(),
        ];
    }
}
