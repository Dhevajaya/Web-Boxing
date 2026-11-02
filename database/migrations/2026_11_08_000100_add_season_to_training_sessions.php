<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Latihan v2: rekor & peringkat per game per season, dan jumlah bintang per sesi.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('season')->default(0)->after('game_date');
            $table->unsignedTinyInteger('stars')->default(0)->after('bonus');
            $table->index(['game', 'season', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropIndex(['game', 'season', 'score']);
            $table->dropColumn(['season', 'stars']);
        });
    }
};
