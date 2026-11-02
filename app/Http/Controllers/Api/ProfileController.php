<?php

namespace App\Http\Controllers\Api;

use App\Game\Looks;
use App\Game\NameRules;
use App\Game\Progression;
use App\Game\Rules;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Fighter;
use App\Support\GameConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Profil milik pemain yang sedang masuk: tampilan avatar, julukan & kota, poin stat.
 * Semua aturan ada di kelas murni (App\Game\Looks, NameRules, Progression);
 * controller ini hanya menyimpan dan mencatat.
 */
class ProfileController extends Controller
{
    private const NAME_ERRORS = [
        'too_short' => ':field minimal :min huruf.',
        'too_long' => ':field maksimal :max huruf.',
        'bad_chars' => ':field hanya boleh berisi huruf, angka, spasi, titik, tanda petik, dan tanda hubung.',
        'no_letter' => ':field harus mengandung huruf.',
        'banned' => ':field mengandung kata yang tidak diizinkan.',
    ];

    public function __construct(
        private readonly Looks $looks,
        private readonly GameConfig $game,
    ) {}

    /** GET /api/me/profile */
    public function show()
    {
        return $this->ok($this->payload($this->me()))->header('Cache-Control', 'no-store');
    }

    /** PUT /api/me/look  { look: {skin, hair, ...} }  atau  { look: null } untuk kembali ke bawaan */
    public function updateLook(Request $request)
    {
        $f = $this->me();
        $input = $request->input('look');

        if ($input === null && $request->has('look')) {
            $f->forceFill(['look' => null])->save();

            return $this->ok($this->payload($f));
        }
        if (! is_array($input) || array_is_list($input)) {
            return $this->fail('invalid', 'Data tampilan tidak valid.');
        }

        $r = $this->looks->validate($input, $f->resolvedLook(), $f->levelNumber(), $f->supporter_tier);
        if ($r['errors']) {
            $key = array_key_first($r['errors']);
            $message = match ($r['errors'][$key]) {
                'locked' => 'Item itu baru terbuka di Level '.$this->looks->unlockLevel($key, (string) $input[$key]).'.',
                'supporter' => 'Item itu khusus pendukung (Dukung).',
                default => 'Ada pilihan tampilan yang tidak dikenal.',
            };

            return $this->fail('invalid_look', $message, 422, ['fields' => $r['errors']]);
        }

        $f->forceFill(['look' => $r['look']])->save();

        return $this->ok($this->payload($f));
    }

    /** PUT /api/me/identity  { nick: "Si Kilat" | null, kota: "Bandung" | null }  (kosong = bawaan) */
    public function updateIdentity(Request $request, NameRules $names)
    {
        $f = $this->me();
        $cfg = $this->game->get('profile');

        foreach (['nick', 'kota'] as $k) {
            if ($request->filled($k) && ! is_string($request->input($k))) {
                return $this->fail('invalid', 'Data tidak valid.');
            }
        }

        [$nick, $nickErr] = $names->nick($request->input('nick'));
        [$kota, $kotaErr] = $names->kota($request->input('kota'));
        $fields = array_filter(['nick' => $nickErr, 'kota' => $kotaErr]);
        if ($fields) {
            $key = array_key_first($fields);
            $message = strtr(self::NAME_ERRORS[$fields[$key]], [
                ':field' => $key === 'nick' ? 'Julukan' : 'Kota',
                ':min' => $cfg[$key.'_min'],
                ':max' => $cfg[$key.'_max'],
            ]);

            return $this->fail('invalid_name', $message, 422, ['fields' => $fields]);
        }

        if ($nick === $f->nick && $kota === $f->kota) {
            return $this->ok($this->payload($f));
        }

        $next = $this->identityNextAt($f);
        if ($next) {
            return $this->fail('cooldown', 'Julukan dan kota baru bisa diganti lagi nanti.', 429, ['retry_at' => $next->toIso8601String()]);
        }

        AuditLog::record('identity_changed', $f, [
            'from' => ['nick' => $f->nick, 'kota' => $f->kota],
            'to' => ['nick' => $nick, 'kota' => $kota],
        ], 'fighter', $f->id);
        $f->forceFill(['nick' => $nick, 'kota' => $kota, 'identity_changed_at' => now()])->save();

        return $this->ok($this->payload($f));
    }

    /** PUT /api/me/points  { points: {power: 3, speed: 2, ...} } */
    public function updatePoints(Request $request)
    {
        $input = $request->input('points');
        if (! is_array($input) || ($input !== [] && array_is_list($input))) {
            return $this->fail('invalid', 'Data poin tidak valid.');
        }

        $prog = Progression::fromConfig();
        $days = (int) $this->game->get('points.respec_days');

        return DB::transaction(function () use ($input, $prog, $days) {
            // Kunci baris agar dua permintaan bersamaan tidak sama-sama lolos jeda pindah poin.
            $f = Fighter::whereKey($this->me()->getKey())->lockForUpdate()->firstOrFail();
            $base = $f->baseFor();
            $lv = $prog->level((int) $f->xp)['lv'];

            $error = $prog->checkPoints($input, $lv, $base, $this->statMax());
            if ($error) {
                return $this->fail($error, match ($error) {
                    'over_budget' => 'Poin yang dibagikan melebihi jatahmu ('.$prog->pointsFor($lv).').',
                    'over_max' => 'Stat tidak boleh melewati '.$this->statMax().'.',
                    default => 'Data poin tidak valid.',
                });
            }

            $old = $this->normalizePoints($f->points ?? [], $base);
            $new = $this->normalizePoints($input, $base);
            if ($old === $new) {
                return $this->ok($this->payload($f));
            }

            $respec = Progression::isRespec($old, $new);
            if ($respec && ($next = $this->respecNextAt($f, $days))) {
                return $this->fail('respec_locked', "Poin yang sudah dipasang hanya bisa dipindah sekali tiap {$days} hari. Menambah poin baru tetap boleh.", 429, [
                    'retry_at' => $next->toIso8601String(),
                ]);
            }

            AuditLog::record('points_changed', $f, ['from' => $old, 'to' => $new, 'respec' => $respec], 'fighter', $f->id);
            $f->forceFill(['points' => $new] + ($respec ? ['points_respec_at' => now()] : []))->save();

            return $this->ok($this->payload($f));
        });
    }

    /** PUT /api/me/archetype  { archetype: "raja_tendang" }  Jeda points.archetype_days, ganti pertama bebas. */
    public function updateArchetype(Request $request)
    {
        $id = $request->input('archetype');
        $R = app(Rules::class)->version();
        $known = array_column($R['archetypes'] ?? [], null, 'id');
        if (! is_string($id) || ! isset($known[$id])) {
            return $this->fail('invalid', 'Gaya bertarung tidak dikenal.');
        }

        return DB::transaction(function () use ($id) {
            $f = Fighter::whereKey($this->me()->getKey())->lockForUpdate()->firstOrFail();
            if ($f->archetypeId() === $id) {
                return $this->ok($this->payload($f));
            }
            if ($next = $this->archetypeNextAt($f)) {
                return $this->fail('cooldown', 'Gaya bertarung baru bisa diganti lagi nanti.', 429, ['retry_at' => $next->toIso8601String()]);
            }
            AuditLog::record('archetype_changed', $f, ['from' => $f->archetypeId(), 'to' => $id], 'fighter', $f->id);
            // Jurus khas ikut archetype: pilihan lama dikosongkan (otomatis jurus khas yang baru).
            $f->forceFill(['archetype' => $id, 'archetype_changed_at' => now(), 'jurus' => null])->save();

            return $this->ok($this->payload($f));
        });
    }

    /** PUT /api/me/jurus  { jurus: ["gebuk_beruntun", "sambar_badan"] } */
    public function updateJurus(Request $request, \App\Game\Jurus $jurus)
    {
        $f = Fighter::findOrFail($this->me()->getKey());
        $chosen = $request->input('jurus');
        $error = $jurus->invalid($f->archetypeId(), $f->levelNumber(), $chosen);
        if ($error) {
            return $this->fail('invalid', $error);
        }
        $f->forceFill(['jurus' => array_values($chosen)])->save();

        return $this->ok($this->payload($f->refresh()));
    }

    // ---------------------------------------------------------------- bantuan

    private function archetypeNextAt(Fighter $f): ?\Carbon\CarbonInterface
    {
        $at = $f->archetype_changed_at?->copy()->addDays((int) $this->game->get('points.archetype_days'));

        return $at?->isFuture() ? $at : null;
    }

    private function me(): Fighter
    {
        /** @var Fighter */
        return Auth::guard('fighter')->user();
    }

    private function statMax(): int
    {
        return (int) app(Rules::class)->version()['statMax'];
    }

    /** Semua kunci stat ada, berurutan, bernilai int. */
    private function normalizePoints(array $points, array $base): array
    {
        $out = [];
        foreach (array_keys($base) as $k) {
            $out[$k] = (int) ($points[$k] ?? 0);
        }

        return $out;
    }

    private function identityNextAt(Fighter $f): ?\Carbon\CarbonInterface
    {
        $at = $f->identity_changed_at?->copy()->addHours((int) $this->game->get('profile.identity_cooldown_hours'));

        return $at?->isFuture() ? $at : null;
    }

    private function respecNextAt(Fighter $f, int $days): ?\Carbon\CarbonInterface
    {
        $at = $f->points_respec_at?->copy()->addDays($days);

        return $at?->isFuture() ? $at : null;
    }

    private function payload(Fighter $f): array
    {
        $prog = Progression::fromConfig();
        $level = $prog->level((int) $f->xp);
        $b = $f->base();
        $base = $f->baseFor();
        $points = $this->normalizePoints($f->points ?? [], $base);
        $days = (int) $this->game->get('points.respec_days');
        $R = app(Rules::class)->version();
        $arch = $f->archetypeId();
        $archDef = collect($R['archetypes'] ?? [])->firstWhere('id', $arch);
        $jurus = app(\App\Game\Jurus::class);

        return [
            'username' => $f->instagram_username,
            'verified' => (bool) $f->verified_at,
            'xp' => (int) $f->xp,
            'level' => $level,
            'look' => $f->resolvedLook(),
            'lookCustom' => (bool) $f->look,
            'nick' => $f->nick,
            'kota' => $f->kota,
            'identityNextAt' => $this->identityNextAt($f)?->toIso8601String(),
            'defaults' => [
                'look' => $this->looks->default($f->instagram_username),
                'nick' => $b['nick'],
                'kota' => $b['kota'],
            ],
            'style' => $arch,
            'styleName' => $archDef['name'] ?? $b['styleName'],
            'archetypes' => collect($R['archetypes'] ?? [])->map(fn ($a) => [
                'id' => $a['id'], 'name' => $a['name'], 'en' => $a['en'], 'desc' => $a['desc'],
                'passive' => ['name' => $a['passive']['name'], 'desc' => $a['passive']['desc']], 'signature' => $a['signature'],
            ])->values(),
            'archetypeDefault' => $b['style'],
            'archetypeNextAt' => $this->archetypeNextAt($f)?->toIso8601String(),
            'archetypeDays' => (int) $this->game->get('points.archetype_days'),
            'jurus' => collect($jurus->catalog($arch, $level['lv']))->map(fn ($j) => [
                'id' => $j['id'], 'name' => $j['name'], 'desc' => $j['desc'], 'kind' => $j['kind'],
                'unlock' => $j['unlock'], 'open' => $j['open'], 'steps' => $j['steps'], 'meter' => $j['meter'],
            ])->values(),
            'jurusEquipped' => $f->equippedJurus(),
            'jurusSlots' => (int) ($R['jurusSlots'] ?? 0),
            'base' => $base,
            'points' => $points,
            'pointsTotal' => $prog->pointsFor($level['lv']),
            'pointsUsed' => array_sum($points),
            'statMax' => $this->statMax(),
            'respecDays' => $days,
            'respecNextAt' => $this->respecNextAt($f, $days)?->toIso8601String(),
            'sasana' => $f->sasana?->only(['slug', 'name', 'icon', 'color']),
            'sasanaLocked' => $f->sasana_id && (int) $f->sasana_season === $this->game->currentSeasonNumber(),
            'supporter' => $f->supporter_tier,
            'supporterAnonymous' => (bool) $f->supporter_anonymous,
        ];
    }
}
