<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATS = ['power', 'speed', 'stamina', 'defense', 'chin'];

    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();  // 0 = pra-musim
            $table->string('name', 64);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 12)->default('upcoming'); // upcoming, active, closed
            $table->json('config_snapshot')->nullable();        // salinan config/game.php saat ditutup
            $table->timestamps();
        });

        Schema::create('fight_night_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('slot_at')->unique();
            $table->string('status', 16)->default('preparing'); // preparing, simulating, ready, publishing, done, failed
            $table->unsignedInteger('pairs_total')->default(0);
            $table->unsignedInteger('cursor')->default(0);
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();
        });

        // Satu laga = input simulasi saja (stat, gaya, seed, versi). Kejadian ronde
        // diputar ulang dari input ini, jadi tidak ada log aksi yang disimpan.
        Schema::create('fights', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12);                       // night, challenge, cup
            $table->timestamp('slot_at')->nullable();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('a_id')->constrained('fighters')->cascadeOnDelete();
            $table->foreignId('b_id')->constrained('fighters')->cascadeOnDelete();
            $table->unsignedInteger('seed');
            $table->unsignedTinyInteger('engine_version');
            $table->string('a_style', 16);
            $table->string('b_style', 16);
            foreach (['a', 'b'] as $side) {
                foreach (self::STATS as $k) {
                    $table->unsignedTinyInteger("{$side}_{$k}");
                }
            }
            $table->string('winner', 4);                       // a, b, draw
            $table->string('how', 8);                          // KO, TKO, Angka
            $table->unsignedTinyInteger('round');
            $table->unsignedTinyInteger('score_a')->default(0);
            $table->unsignedTinyInteger('score_b')->default(0);
            $table->unsignedSmallInteger('xp_a')->default(0);
            $table->unsignedSmallInteger('xp_b')->default(0);
            $table->timestamp('visible_at')->useCurrent();
            $table->timestamp('applied_at')->nullable();       // hasil sudah diterapkan ke rekor (idempoten)
            $table->timestamps();

            $table->index(['a_id', 'visible_at']);
            $table->index(['b_id', 'visible_at']);
            $table->index(['kind', 'slot_at']);
        });

        Schema::create('fighter_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('w')->default(0);
            $table->unsignedInteger('l')->default(0);
            $table->unsignedInteger('d')->default(0);
            $table->unsignedInteger('ko')->default(0);
            $table->unsignedSmallInteger('stars')->default(0);
            $table->foreignId('last_fight_id')->nullable()->constrained('fights')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fighter_id', 'season_id']);
            $table->index(['season_id', 'stars', 'w']);
        });

        Schema::create('weekly_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');                       // Senin (WIB)
            $table->unsignedSmallInteger('w')->default(0);
            $table->unsignedSmallInteger('l')->default(0);
            $table->unsignedSmallInteger('d')->default(0);
            $table->unsignedSmallInteger('ko')->default(0);
            $table->timestamps();

            $table->unique(['fighter_id', 'week_start']);
            $table->index(['week_start', 'w', 'l']);
        });

        Schema::create('season_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->unsignedInteger('w');
            $table->unsignedInteger('l');
            $table->unsignedInteger('d');
            $table->unsignedSmallInteger('stars');
            $table->timestamps();

            $table->unique(['season_id', 'fighter_id']);
            $table->index(['season_id', 'rank']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('code', 48);                       // perintis:s0, juara:s0, piala:2026-11-02
            $table->json('meta')->nullable();
            $table->timestamp('awarded_at');

            $table->unique(['fighter_id', 'code']);
        });

        // Semua XP lewat buku besar dengan kunci unik, jadi job yang diulang tidak menggandakan XP.
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);                     // night, challenge, cup, training, quest, admin
            $table->string('source_id', 64);
            $table->integer('amount');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source', 'source_id', 'fighter_id']);
        });
    }

    public function down(): void
    {
        foreach (['xp_ledger', 'badges', 'season_results', 'weekly_records', 'fighter_records', 'fights', 'fight_night_runs', 'seasons'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
