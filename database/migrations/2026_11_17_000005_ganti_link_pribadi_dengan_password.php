<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi PRD bagian 2 (17 Nov 2026): link pribadi dan PIN diganti login email + password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['token_hash', 'token_dibuat_pada', 'pin_hash']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Null sampai warga membuat password lewat email "Buat password".
            $table->string('password')->nullable()->after('aktif');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_dibuat_pada')->nullable();
            $table->string('pin_hash')->nullable();
        });
    }
};
