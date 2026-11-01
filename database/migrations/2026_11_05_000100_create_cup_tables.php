<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 7: Piala Mingguan (turnamen gugur). Satu piala per minggu (Senin WIB).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cups', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique();
            $table->string('status', 12)->default('open');     // open, drawn, done, cancelled
            $table->unsignedInteger('size')->default(0);        // pangkat 2
            $table->unsignedTinyInteger('rounds')->default(0);
            $table->unsignedInteger('entrants')->default(0);
            $table->foreignId('champion_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cup_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('status', 4)->default('in');         // in, out (mundur sebelum undian)
            $table->boolean('auto')->default(false);            // didaftarkan otomatis saat undian
            $table->unsignedInteger('seed')->nullable();
            $table->unsignedTinyInteger('out_round')->nullable(); // kalah di babak ini; null = masih bertahan / juara
            $table->timestamps();

            $table->unique(['cup_id', 'fighter_id']);
            $table->index(['cup_id', 'status']);
        });

        Schema::create('cup_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedInteger('slot');
            $table->foreignId('a_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->foreignId('b_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->foreignId('fight_id')->nullable()->constrained('fights')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->boolean('walkover')->default(false);
            $table->timestamp('scheduled_at');
            $table->timestamps();

            $table->unique(['cup_id', 'round', 'slot']);
            $table->index(['cup_id', 'scheduled_at']);
            $table->index(['a_id']);
            $table->index(['b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cup_matches');
        Schema::dropIfExists('cup_entries');
        Schema::dropIfExists('cups');
    }
};
