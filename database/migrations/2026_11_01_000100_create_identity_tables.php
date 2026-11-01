<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasanas', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 48);
            $table->string('icon', 16)->nullable();
            $table->string('color', 16)->nullable();
            $table->string('motto', 120)->nullable();
            $table->timestamps();
        });

        // Hanya petarung yang SUDAH daftar (atau NPC) yang punya baris.
        // Username lain tetap punya kartu turunan dari hash, tanpa baris di sini.
        Schema::create('fighters', function (Blueprint $table) {
            $table->id();
            $table->string('instagram_username', 30)->unique();
            $table->string('ig_scoped_id', 64)->nullable()->unique(); // IGSID dari webhook Meta
            $table->string('status', 16)->default('active');          // active, suspended, deleted
            $table->boolean('is_npc')->default(false);
            $table->json('look')->nullable();                          // pilihan tampilan avatar
            $table->string('nick', 40)->nullable();                    // null = julukan bawaan dari hash
            $table->string('kota', 48)->nullable();
            $table->json('points')->nullable();                        // poin stat yang dibagikan
            $table->timestamp('points_respec_at')->nullable();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedTinyInteger('level')->default(1);
            $table->foreignId('sasana_id')->nullable()->constrained('sasanas')->nullOnDelete();
            $table->unsignedSmallInteger('sasana_season')->nullable();
            $table->string('supporter_tier', 16)->nullable();          // tribun, ringside, vip
            $table->boolean('supporter_anonymous')->default(false);
            $table->timestamp('verified_at')->nullable();              // identitas IG terbukti
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_active_at']);
            $table->index(['is_npc', 'status']);
        });

        Schema::create('fighter_username_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->string('old_username', 30)->index();
            $table->timestamp('changed_at');
        });

        Schema::create('login_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('method', 12);                       // ref, dm_link, code, manual, dev
            $table->char('ref_hash', 64)->nullable()->unique();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->char('code_hash', 64)->nullable()->index();
            $table->string('requested_username', 30)->nullable();
            $table->string('ig_scoped_id', 64)->nullable();
            $table->foreignId('fighter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');  // pending, ok, not_follower, unreadable, used, expired, rejected
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('player_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->char('session_id_hash', 64)->unique();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_sessions');
        Schema::dropIfExists('login_requests');
        Schema::dropIfExists('fighter_username_history');
        Schema::dropIfExists('fighters');
        Schema::dropIfExists('sasanas');
    }
};
