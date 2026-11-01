<?php

namespace Database\Seeders;

use App\Game\Rules;
use App\Models\Admin;
use App\Models\Fighter;
use App\Models\Sasana;
use App\Models\Season;
use Illuminate\Database\Seeder;

/**
 * Data awal game. Aman dijalankan berulang (updateOrCreate).
 *   php artisan db:seed --class=GameSeeder
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('game.sasana') as $s) {
            Sasana::updateOrCreate(['slug' => $s['slug']], $s);
        }

        if (! Season::where('number', 0)->exists()) {
            Season::create([
                'number' => 0,
                'name' => 'Season 0 · Pra-musim',
                'starts_at' => now(),
                'status' => Season::where('status', 'active')->exists() ? 'closed' : 'active',
            ]);
        }

        // NPC: lawan cadangan saat jumlah pemain ganjil atau masih sedikit.
        foreach (app(Rules::class)->content['npcs'] as $username) {
            Fighter::updateOrCreate(
                ['instagram_username' => $username],
                ['is_npc' => true, 'status' => 'active', 'activated_at' => now()],
            );
        }

        // Admin pertama dari .env (opsional): ADMIN_EMAIL dan ADMIN_PASSWORD.
        if (($email = config('game.admin_seed.email')) && ($password = config('game.admin_seed.password'))) {
            Admin::firstOrCreate(['email' => $email], [
                'name' => 'Owner', 'password' => $password, 'role' => 'owner',
            ]);
        }

        $this->command?->info('Sasana, Season 0, dan '.count(app(Rules::class)->content['npcs']).' NPC siap.');
    }
}
