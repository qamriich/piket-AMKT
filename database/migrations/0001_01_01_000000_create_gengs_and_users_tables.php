<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gengs', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
            // Urutan giliran rotasi (1, 2, 3). Dipakai oleh aturan B1.
            $table->unsignedTinyInteger('urutan')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('no_kamar', 20)->nullable();
            $table->foreignId('geng_id')->nullable()->constrained('gengs')->nullOnDelete();
            $table->enum('role', ['warga', 'ketua', 'sekretaris', 'bendahara', 'tata_tertib'])->default('warga');
            $table->boolean('wajib_diperiksa')->default(false);
            $table->boolean('aktif')->default(true);
            // SHA-256 dari token link pribadi (64 karakter hex). Token asli tidak pernah disimpan.
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_dibuat_pada')->nullable();
            $table->string('pin_hash')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['aktif', 'geng_id']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('gengs');
    }
};
