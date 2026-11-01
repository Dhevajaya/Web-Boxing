<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 10: catatan donasi (Dukung). Webhook idempoten lewat (provider, external_id).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);                    // saweria, manual
            $table->string('external_id', 96);
            $table->foreignId('fighter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 30)->nullable();        // @username yang ditulis di pesan
            $table->string('donor_name', 80)->nullable();
            $table->unsignedInteger('amount');
            $table->string('message', 255)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'external_id']);
            $table->index(['fighter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
