<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 5: kode pendek untuk link tantangan (/t/{code}) dan pengumuman Mading.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fights', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->unique()->after('kind');
            $table->index(['kind', 'a_id', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamp('published_at')->nullable();      // null = draf
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['published_at', 'pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::table('fights', function (Blueprint $table) {
            $table->dropIndex(['kind', 'a_id', 'created_at']);
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
