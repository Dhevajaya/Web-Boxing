<?php

use App\Game\FighterFactory;
use App\Game\FightSimulator;
use Illuminate\Support\Facades\Artisan;

/*
| Jadwal (scheduler) diisi mulai Fase 4: Fight Night tiap 6 jam, Piala, rekap mingguan.
| Di shared hosting cukup SATU cron tiap menit:
|   * * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1
*/

Artisan::command('game:simulate {a} {b} {--seed=1} {--v=}', function (FighterFactory $factory, FightSimulator $sim) {
    $v = $this->option('v') ? (int) $this->option('v') : null;
    $fa = $factory->base($this->argument('a'), $v);
    $fb = $factory->base($this->argument('b'), $v);
    $r = $sim->simulate(
        ['style' => $fa['style'], 'stats' => $fa['base']],
        ['style' => $fb['style'], 'stats' => $fb['base']],
        (int) $this->option('seed'),
        $v,
    );

    $this->table(['', '@'.$fa['username'], '@'.$fb['username']], [
        ['Gaya', $fa['styleName'], $fb['styleName']],
        ...array_map(fn ($k) => [$k, $fa['base'][$k], $fb['base'][$k]], array_keys($fa['base'])),
        ['OVR', $factory->ovr($fa['base'], $v), $factory->ovr($fb['base'], $v)],
    ]);

    foreach ($r['events'] as $e) {
        $this->line(sprintf('R%d %-5s %-2s %-9s dmg=%-4s HP %4d - %-4d', $e['rd'], $e['t'], $e['who'] ?? '', $e['p'] ?? '', $e['d'] ?? '', $e['ha'], $e['hb']));
    }
    $winner = $r['win'] === 'draw' ? 'SERI' : '@'.($r['win'] === 'a' ? $fa['username'] : $fb['username']);
    $this->info("Hasil: {$winner} · {$r['how']} ronde {$r['rd']} · poin {$r['scoreA']}-{$r['scoreB']} · sig ".FightSimulator::signature($r));
})->purpose('Simulasikan laga dua username (tanpa menyimpan apa pun)');
