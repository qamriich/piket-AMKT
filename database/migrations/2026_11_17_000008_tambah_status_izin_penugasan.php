<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD bagian 15 R4: status baru "izin" untuk piket yang dilepas karena izin pulang (R3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan', function (Blueprint $table) {
            $table->enum('status', ['terjadwal', 'bayar_bintang', 'selesai', 'bintang', 'lunas', 'izin'])
                ->default('terjadwal')->change();
        });
    }

    public function down(): void
    {
        Schema::table('penugasan', function (Blueprint $table) {
            $table->enum('status', ['terjadwal', 'bayar_bintang', 'selesai', 'bintang', 'lunas'])
                ->default('terjadwal')->change();
        });
    }
};
