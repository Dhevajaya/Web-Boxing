<?php

namespace Tests\Unit\Game;

use App\Game\Looks;
use App\Game\NameRules;
use App\Game\Progression;
use PHPUnit\Framework\TestCase;

/** Kelas murni Fase 3: tampilan avatar, julukan/kota, dan pembagian poin stat. */
class ProfileRulesTest extends TestCase
{
    private static array $cfg;

    public static function setUpBeforeClass(): void
    {
        self::$cfg = require __DIR__.'/../../../config/game.php';
    }

    private function looks(): Looks
    {
        return Looks::load(__DIR__.'/../../../resources/game');
    }

    private function names(): NameRules
    {
        return new NameRules(self::$cfg['profile']);
    }

    private function prog(): Progression
    {
        $c = self::$cfg;

        return new Progression(['points' => $c['points'], 'level' => $c['level'], 'tiers' => $c['tiers'], 'xp' => $c['xp']]);
    }

    // ------------------------------------------------------------- tampilan

    public function test_default_look_is_deterministic_and_complete(): void
    {
        $l = $this->looks();
        $a = $l->default('Budi');
        $this->assertSame($a, $l->default('budi'));
        $this->assertSame($l->keys(), array_keys($a));
        $this->assertNotSame($a, $l->default('siti_99')); // sangat kecil peluangnya sama persis
    }

    public function test_resolve_ignores_unknown_items(): void
    {
        $l = $this->looks();
        $look = $l->resolve(['skin' => 'gelap', 'hair' => 'tidak_ada', 'sayap' => 'emas'], 'budi');
        $this->assertSame('gelap', $look['skin']);
        $this->assertSame($l->default('budi')['hair'], $look['hair']);
        $this->assertArrayNotHasKey('sayap', $look);
    }

    public function test_validate_unlocks_by_level(): void
    {
        $l = $this->looks();
        $cur = $l->default('budi');

        $r = $l->validate(['gloves' => 'emas', 'hair' => 'cepak'], $cur, 1);
        $this->assertSame('locked', $r['errors']['gloves']);
        $this->assertSame('cepak', $r['look']['hair']);

        $r = $l->validate(['gloves' => 'emas'], $cur, 10);
        $this->assertSame([], $r['errors']);
        $this->assertSame('emas', $r['look']['gloves']);

        // Item terkunci yang sudah dipakai boleh dipertahankan.
        $r = $l->validate(['gloves' => 'emas'], ['gloves' => 'emas'] + $cur, 1);
        $this->assertSame([], $r['errors']);

        $r = $l->validate(['sayap' => 'x', 'skin' => 123, 'face' => 'tidak_ada'], $cur, 20);
        $this->assertSame(['sayap' => 'unknown_part', 'skin' => 'unknown_item', 'face' => 'unknown_item'], $r['errors']);
    }

    // ------------------------------------------------------------ julukan

    public function test_nick_rules(): void
    {
        $n = $this->names();
        $this->assertSame(['Si Tangan Kilat', null], $n->nick('  Si   Tangan Kilat '));
        $this->assertSame(["Jo'Ko Pukul-Kanan", null], $n->nick("Jo'Ko Pukul-Kanan"));
        $this->assertSame(['Ñoño Élan', null], $n->nick('Ñoño Élan'));
        $this->assertSame([null, null], $n->nick('   '));      // kosong = pakai bawaan
        $this->assertSame([null, 'too_short'], $n->nick('ab'));
        $this->assertSame([null, 'too_long'], $n->nick(str_repeat('a', 25)));
        $this->assertSame([null, 'bad_chars'], $n->nick('cek @akunku'));
        $this->assertSame([null, 'bad_chars'], $n->nick('juara 🥊'));
        $this->assertSame([null, 'no_letter'], $n->nick('123 456'));
    }

    public function test_banned_words(): void
    {
        $n = $this->names();
        foreach (['Anjing Gila', '4NJ1NG', 'si anjiiing', 'Admin Resmi', 'raja asu', 'b a n g s a t'] as $bad) {
            $this->assertSame('banned', $n->nick($bad)[1], $bad);
        }
        foreach (['Raja Asuransi', 'Babinsa Ring', 'Petai Goreng', 'Si Kilat'] as $ok) {
            $this->assertNull($n->nick($ok)[1], $ok);
        }
    }

    // -------------------------------------------------------------- poin

    public function test_points_check(): void
    {
        $p = $this->prog();
        $base = ['power' => 70, 'speed' => 50, 'stamina' => 50, 'defense' => 50, 'chin' => 50];

        $this->assertNull($p->checkPoints(['power' => 14, 'speed' => 6], 1, $base, 99));
        $this->assertSame('over_budget', $p->checkPoints(['power' => 15, 'speed' => 6], 1, $base, 99));
        $this->assertSame('invalid', $p->checkPoints(['luck' => 1], 1, $base, 99));
        $this->assertSame('invalid', $p->checkPoints(['power' => '3'], 1, $base, 99));
        $this->assertSame('invalid', $p->checkPoints(['power' => -1], 1, $base, 99));
        $this->assertSame('over_max', $p->checkPoints(['power' => 30], 20, $base, 99));
        $this->assertNull($p->checkPoints(['power' => 29], 20, $base, 99));
    }

    public function test_respec_detection(): void
    {
        $this->assertFalse(Progression::isRespec([], ['power' => 3]));
        $this->assertFalse(Progression::isRespec(['power' => 3], ['power' => 3, 'speed' => 2]));
        $this->assertTrue(Progression::isRespec(['power' => 3], ['power' => 2, 'speed' => 1]));
        $this->assertTrue(Progression::isRespec(['power' => 3], []));
    }
}
