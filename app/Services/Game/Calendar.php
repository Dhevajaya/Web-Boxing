<?php

namespace App\Services\Game;

use App\Support\GameConfig;
use Carbon\CarbonImmutable;

/**
 * Waktu-waktu game. Jadwal ditulis dalam zona game (WIB), disimpan di database dalam UTC.
 */
class Calendar
{
    public function __construct(private readonly GameConfig $game) {}

    public function tz(): string
    {
        return (string) $this->game->get('brand.timezone', 'Asia/Jakarta');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    /** Slot Fight Night pertama yang >= $at. */
    public function nextSlot(?CarbonImmutable $at = null): CarbonImmutable
    {
        $at = ($at ?? $this->now())->setTimezone($this->tz());
        $hours = $this->hours();
        for ($day = 0; $day < 3; $day++) {
            $d = $at->startOfDay()->addDays($day);
            foreach ($hours as $h) {
                $slot = $d->setTime($h, 0);
                if ($slot->greaterThanOrEqualTo($at)) {
                    return $slot->utc();
                }
            }
        }

        throw new \RuntimeException('Jadwal Fight Night kosong.');
    }

    /** Slot Fight Night terakhir yang <= $at. */
    public function prevSlot(?CarbonImmutable $at = null): CarbonImmutable
    {
        $at = ($at ?? $this->now())->setTimezone($this->tz());
        $hours = array_reverse($this->hours());
        for ($day = 0; $day < 3; $day++) {
            $d = $at->startOfDay()->subDays($day);
            foreach ($hours as $h) {
                $slot = $d->setTime($h, 0);
                if ($slot->lessThanOrEqualTo($at)) {
                    return $slot->utc();
                }
            }
        }

        throw new \RuntimeException('Jadwal Fight Night kosong.');
    }

    /** Senin (zona game) dari minggu tanggal ini, format Y-m-d. */
    public function weekStart(?CarbonImmutable $at = null): string
    {
        return ($at ?? $this->now())->setTimezone($this->tz())->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }

    /** Tanggal hari ini di zona game. */
    public function gameDate(?CarbonImmutable $at = null): string
    {
        return ($at ?? $this->now())->setTimezone($this->tz())->toDateString();
    }

    /** Awal hari (zona game) dalam UTC. */
    public function dayStart(?CarbonImmutable $at = null): CarbonImmutable
    {
        return ($at ?? $this->now())->setTimezone($this->tz())->startOfDay()->utc();
    }

    /** @return list<int> */
    private function hours(): array
    {
        $h = array_map('intval', (array) $this->game->get('fight_night.hours', [0, 6, 12, 18]));
        sort($h);

        return $h;
    }
}
