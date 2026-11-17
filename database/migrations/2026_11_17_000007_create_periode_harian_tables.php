<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD bagian 15 R1: jadwal harian per periode (misalnya satu semester), bukan per minggu.
 * Untuk tiap geng, tiap anggota punya satu hari tetap; hari tanpa anggota kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_harian', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->timestamps();

            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });

        Schema::create('periode_harian_hari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_harian_id')->constrained('periode_harian')->cascadeOnDelete();
            $table->foreignId('geng_id')->constrained('gengs')->cascadeOnDelete();
            // 1 = Senin ... 7 = Minggu (ISO-8601).
            $table->unsignedTinyInteger('hari');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Satu hari maksimal satu orang per geng, dan satu orang maksimal satu hari per periode.
            $table->unique(['periode_harian_id', 'geng_id', 'hari']);
            $table->unique(['periode_harian_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_harian_hari');
        Schema::dropIfExists('periode_harian');
    }
};
