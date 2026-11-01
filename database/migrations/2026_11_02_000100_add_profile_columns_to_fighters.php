<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 3: kolom look, nick, kota, points, dan points_respec_at sudah ada sejak Fase 1.
| Yang ditambah hanya penanda waktu ganti julukan/kota (untuk jeda ganti identitas).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fighters', function (Blueprint $table) {
            $table->timestamp('identity_changed_at')->nullable()->after('kota');
        });
    }

    public function down(): void
    {
        Schema::table('fighters', function (Blueprint $table) {
            $table->dropColumn('identity_changed_at');
        });
    }
};
