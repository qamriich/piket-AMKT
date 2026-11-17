<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('jenis', ['harian', 'kamis_minggu']);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif_default')->default(true);
            $table->timestamps();
            // Tugas yang dihapus disembunyikan, supaya riwayat penugasan lama tetap utuh.
            $table->softDeletes();

            $table->index(['jenis', 'urutan']);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('tugas');
    }
};
