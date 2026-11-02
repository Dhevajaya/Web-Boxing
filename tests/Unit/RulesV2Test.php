<?php

namespace Tests\Unit;

use App\Game\RulesSchema;
use PHPUnit\Framework\TestCase;

class RulesV2Test extends TestCase
{
    private function rules(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../resources/game/rules.v2.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_rules_v2_is_valid(): void
    {
        $this->assertSame([], RulesSchema::errors($this->rules()));
    }

    public function test_rules_v2_matches_decisions(): void
    {
        $r = $this->rules();
        $this->assertCount(10, $r['stats']);
        $this->assertSame(['jago_gebuk', 'raja_tendang', 'ahli_balas', 'jaga_jarak', 'petarung_komplet'], array_column($r['archetypes'], 'id'));
        $cats = array_count_values(array_column($r['techniques'], 'cat'));
        $this->assertSame(4, $cats['hand']);
        $this->assertSame(4, $cats['kick']);
    }

    public function test_schema_catches_mistakes(): void
    {
        $r = $this->rules();
        $r['jurus'][0]['steps'][] = 'tendangan_terbang';
        $r['ovr']['strength'] += 1;
        $r['techniques'][0]['dmg'] = 60.5;
        unset($r['archetypes'][1]['bias']['balance']);
        $errors = implode("\n", RulesSchema::errors($r));
        $this->assertStringContainsString("langkah 'tendangan_terbang' bukan teknik", $errors);
        $this->assertStringContainsString('ovr harus berjumlah 100', $errors);
        $this->assertStringContainsString('pecahan', $errors);
        $this->assertStringContainsString("archetype 'raja_tendang'.bias", $errors);
    }
}
