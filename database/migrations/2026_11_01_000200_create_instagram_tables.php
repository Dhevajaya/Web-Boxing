<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event mentah dari webhook Meta. Webhook hanya menyimpan lalu menjawab 200;
        // pemrosesan dilakukan job agar respons tetap di bawah 1 detik.
        Schema::create('ig_events', function (Blueprint $table) {
            $table->id();
            $table->char('dedupe_key', 64)->unique();   // mid pesan atau hash payload
            $table->string('type', 24);                 // message, postback, referral, other
            $table->string('ig_scoped_id', 64)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['processed_at', 'id']);
        });

        // Antrean balasan DM dengan batas laju.
        Schema::create('ig_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('ig_scoped_id', 64);
            $table->string('kind', 24);                 // login_link, welcome, not_follower, info
            $table->json('body');
            $table->string('status', 12)->default('queued'); // queued, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('send_after')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'send_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ig_outbox');
        Schema::dropIfExists('ig_events');
    }
};
