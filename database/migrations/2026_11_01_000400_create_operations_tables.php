<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nilai yang mengalahkan config/game.php, diubah dari panel admin.
        // key memakai notasi titik (contoh: xp.win), value disimpan sebagai JSON.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 96)->primary();
            $table->json('value');
            $table->timestamps();
        });

        // Admin terpisah dari pemain: login sendiri, password + TOTP.
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 16)->default('moderator'); // owner, moderator, editor
            $table->text('totp_secret')->nullable();          // dienkripsi via cast
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 12);                 // admin, system, fighter
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('data')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('settings');
    }
};
