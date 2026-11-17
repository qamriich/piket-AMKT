<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minggu_piket', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_senin')->unique();
            $table->foreignId('geng_harian_id')->constrained('gengs');
            $table->enum('mode_minggu', ['tugas_kecil', 'kerja_bakti'])->default('tugas_kecil');
            $table->enum('status_harian', ['draf', 'terbit'])->default('draf');
            $table->enum('status_kamis', ['draf', 'terbit'])->default('draf');
            $table->enum('status_minggu', ['draf', 'terbit'])->default('draf');
            $table->timestamps();
        });

        Schema::create('tugas_aktif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minggu_piket_id')->constrained('minggu_piket')->cascadeOnDelete();
            $table->foreignId('tugas_id')->constrained('tugas');
            $table->enum('sesi', ['kamis', 'minggu']);
            $table->timestamps();

            $table->unique(['minggu_piket_id', 'tugas_id', 'sesi']);
        });

        Schema::create('penugasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minggu_piket_id')->constrained('minggu_piket')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            // Null untuk piket harian (satu orang mengerjakan semua tugas harian) dan kerja bakti.
            $table->foreignId('tugas_id')->nullable()->constrained('tugas');
            $table->enum('jenis', ['harian', 'kamis', 'minggu', 'kerja_bakti']);
            $table->date('tanggal');
            $table->dateTime('tenggat');
            $table->enum('status', ['terjadwal', 'bayar_bintang', 'selesai', 'bintang', 'lunas'])->default('terjadwal');
            // Baris Bayar Bintang menunjuk ke baris Bintang yang dilunasinya.
            $table->foreignId('membayar_penugasan_id')->nullable()->constrained('penugasan')->nullOnDelete();
            $table->foreignId('selesai_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('selesai_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // Integritas: satu tugas Kamis/Minggu hanya punya satu petugas per minggu.
            // (tugas_id null pada harian/kerja bakti, jadi tidak terkena aturan ini.)
            $table->unique(['minggu_piket_id', 'tugas_id', 'jenis']);

            // Integritas: satu tanggal harian hanya boleh punya satu penugasan.
            $table->date('tanggal_harian')->nullable()
                ->storedAs("CASE WHEN jenis = 'harian' THEN tanggal ELSE NULL END");
            $table->unique('tanggal_harian');

            $table->index(['status', 'tenggat']);
            $table->index(['user_id', 'tanggal']);
            $table->index(['jenis', 'tanggal']);
        });

        Schema::create('pengajuan_ganti_bintang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_bintang_id')->constrained('penugasan')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('tanggal_diajukan');
            $table->enum('status', ['menunggu', 'diterima', 'ditolak'])->default('menunggu');
            $table->foreignId('diputuskan_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_diajukan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_ganti_bintang');
        Schema::dropIfExists('penugasan');
        Schema::dropIfExists('tugas_aktif');
        Schema::dropIfExists('minggu_piket');
    }
};
