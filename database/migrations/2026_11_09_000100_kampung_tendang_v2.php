<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| KAMPUNG TENDANG (mesin v2). Hanya MENAMBAH kolom; data lama tidak diubah.
| Reset statistik kompetitif dijalankan terpisah: php artisan game:reset-competitive --dry-run
|
| fighters.archetype       : gaya bertarung pilihan (null = bawaan dari username)
| fighters.jurus           : jurus aktif, maks 2 id
| fights.a_snap / b_snap   : snapshot input laga v2 (10 atribut, archetype, jurus) dalam JSON
| fights.summary           : skor per ronde + aksi penting (jatuh, jurus, KO) untuk riwayat dan audit
| seasons.era              : "tinju" untuk season lama, "tendang" untuk era baru
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fighters', function (Blueprint $table) {
            $table->string('archetype', 20)->nullable()->after('points_respec_at');
            $table->timestamp('archetype_changed_at')->nullable()->after('archetype');
            $table->json('jurus')->nullable()->after('archetype_changed_at');
        });
        Schema::table('fights', function (Blueprint $table) {
            $table->json('a_snap')->nullable()->after('b_style');
            $table->json('b_snap')->nullable()->after('a_snap');
            $table->json('summary')->nullable()->after('score_b');
        });
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('era', 12)->default('tendang')->after('name');
        });
        // Season yang sudah ada sebelum migrasi ini adalah era tinju.
        \Illuminate\Support\Facades\DB::table('seasons')->update(['era' => 'tinju']);
    }

    public function down(): void
    {
        Schema::table('seasons', fn (Blueprint $t) => $t->dropColumn('era'));
        Schema::table('fights', fn (Blueprint $t) => $t->dropColumn(['a_snap', 'b_snap', 'summary']));
        Schema::table('fighters', fn (Blueprint $t) => $t->dropColumn(['archetype', 'archetype_changed_at', 'jurus']));
    }
};
