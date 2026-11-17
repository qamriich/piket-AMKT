<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD bagian 15 R3/R3a (revisi 17 Nov malam): izin memakai tanggal + jam.
 * Izin lama (per tanggal) dipindah menjadi jam 00.00 di hari mulai sampai 23.59 di hari selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('izin', function (Blueprint $table) {
            $table->dateTime('mulai')->nullable()->after('user_id');
            $table->dateTime('selesai')->nullable()->after('mulai');
        });

        DB::table('izin')->orderBy('id')->each(function ($baris) {
            DB::table('izin')->where('id', $baris->id)->update([
                'mulai' => substr((string) $baris->tanggal_mulai, 0, 10).' 00:00:00',
                'selesai' => substr((string) $baris->tanggal_selesai, 0, 10).' 23:59:00',
            ]);
        });

        Schema::table('izin', function (Blueprint $table) {
            $table->dropIndex(['status', 'tanggal_mulai', 'tanggal_selesai']);
        });

        Schema::table('izin', function (Blueprint $table) {
            $table->dropColumn(['tanggal_mulai', 'tanggal_selesai']);
        });

        Schema::table('izin', function (Blueprint $table) {
            $table->dateTime('mulai')->nullable(false)->change();
            $table->dateTime('selesai')->nullable(false)->change();
            $table->index(['status', 'mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::table('izin', function (Blueprint $table) {
            $table->date('tanggal_mulai')->nullable()->after('user_id');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
        });

        DB::table('izin')->orderBy('id')->each(function ($baris) {
            DB::table('izin')->where('id', $baris->id)->update([
                'tanggal_mulai' => substr((string) $baris->mulai, 0, 10),
                'tanggal_selesai' => substr((string) $baris->selesai, 0, 10),
            ]);
        });

        Schema::table('izin', function (Blueprint $table) {
            $table->dropIndex(['status', 'mulai', 'selesai']);
        });

        Schema::table('izin', function (Blueprint $table) {
            $table->dropColumn(['mulai', 'selesai']);
            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai']);
        });
    }
};
