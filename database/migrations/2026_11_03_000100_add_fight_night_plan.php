<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 4: daftar pasangan Fight Night disimpan di run agar simulasi bisa dicicil
| per eksekusi cron (game.fight_night.chunk) tanpa memasangkan ulang.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fight_night_runs', function (Blueprint $table) {
            $table->longText('plan')->nullable()->after('cursor');   // JSON: [[a_id, b_id, seed], ...]
            $table->unsignedInteger('applied')->default(0)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('fight_night_runs', function (Blueprint $table) {
            $table->dropColumn(['plan', 'applied']);
        });
    }
};
