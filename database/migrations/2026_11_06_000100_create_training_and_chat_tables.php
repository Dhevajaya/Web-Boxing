<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 9: Latihan (mini-game) dan Markas (chat).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('game', 12);
            $table->string('stat', 12);
            $table->date('game_date');                         // tanggal zona game; bonus berlaku untuk laga hari ini
            $table->char('token_hash', 64)->unique();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('taps')->default(0);
            $table->unsignedSmallInteger('xp')->default(0);
            $table->unsignedTinyInteger('bonus')->default(0);
            $table->timestamps();

            $table->index(['fighter_id', 'game_date']);
            $table->index(['game_date', 'fighter_id', 'stat']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('room', 40);                        // alun, sasana:{slug}
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('body', 200);
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 24)->nullable();   // reports, admin
            $table->timestamp('created_at')->useCurrent();

            $table->index(['room', 'id']);
            $table->index(['fighter_id', 'created_at']);
        });

        Schema::create('chat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['message_id', 'fighter_id']);
        });

        Schema::table('fighters', function (Blueprint $table) {
            $table->timestamp('muted_until')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('fighters', function (Blueprint $table) {
            $table->dropColumn('muted_until');
        });
        Schema::dropIfExists('chat_reports');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('training_sessions');
    }
};
