<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bintang yang dicatat manual oleh pengurus (misalnya utang bintang dari sebelum aplikasi dipakai).
 * Penanda ini membedakannya dari bintang otomatis (B4), supaya hanya bintang manual yang boleh dihapus lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan', function (Blueprint $table) {
            $table->boolean('manual')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropColumn('manual');
        });
    }
};
