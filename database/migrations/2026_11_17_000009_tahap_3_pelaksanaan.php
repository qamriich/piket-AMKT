<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 (pelaksanaan) dan PRD bagian 15 R3:
 * - tabel `izin` (izin pulang);
 * - penanda email di `penugasan` supaya pengingat dan pemberitahuan jadwal tidak terkirim dua kali;
 * - aturan integritas "satu petugas per tugas" dan "satu penugasan per tanggal harian" tidak lagi
 *   menghitung baris berstatus Izin, supaya pengganti bisa dibuat tanpa menghapus riwayat izin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan')->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->foreignId('dibuat_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diputuskan_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->dateTime('pengingat_dikirim_pada')->nullable();
            $table->dateTime('diberitahu_pada')->nullable();
        });

        $sqlite = DB::getDriverName() === 'sqlite';
        $hasil = function ($kolom, string $ekspresi) use ($sqlite) {
            // SQLite tidak bisa menambah kolom STORED lewat ALTER TABLE; kolom VIRTUAL tetap bisa diberi indeks unik.
            return $sqlite ? $kolom->virtualAs($ekspresi) : $kolom->storedAs($ekspresi);
        };

        // Indeks unik baru dulu, baru indeks lama dilepas (MySQL butuh indeks untuk foreign key).
        Schema::table('penugasan', function (Blueprint $table) use ($hasil) {
            $hasil($table->tinyInteger('slot_aktif')->nullable(), "CASE WHEN status = 'izin' THEN NULL ELSE 1 END");
            $hasil($table->date('tanggal_harian_aktif')->nullable(), "CASE WHEN jenis = 'harian' AND status <> 'izin' THEN tanggal ELSE NULL END");
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->unique(['minggu_piket_id', 'tugas_id', 'jenis', 'slot_aktif'], 'penugasan_satu_petugas_per_tugas');
            $table->unique('tanggal_harian_aktif', 'penugasan_satu_harian_per_tanggal');
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropUnique(['minggu_piket_id', 'tugas_id', 'jenis']);
            $table->dropUnique(['tanggal_harian']);
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropColumn('tanggal_harian');
        });
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('penugasan', function (Blueprint $table) use ($sqlite) {
            $kolom = $table->date('tanggal_harian')->nullable();
            $ekspresi = "CASE WHEN jenis = 'harian' THEN tanggal ELSE NULL END";
            $sqlite ? $kolom->virtualAs($ekspresi) : $kolom->storedAs($ekspresi);
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->unique(['minggu_piket_id', 'tugas_id', 'jenis']);
            $table->unique('tanggal_harian');
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropUnique('penugasan_satu_petugas_per_tugas');
            $table->dropUnique('penugasan_satu_harian_per_tanggal');
        });

        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropColumn(['slot_aktif', 'tanggal_harian_aktif', 'pengingat_dikirim_pada', 'diberitahu_pada']);
        });

        Schema::dropIfExists('izin');
    }
};
