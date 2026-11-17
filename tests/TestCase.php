<?php

namespace Tests;

use App\Enums\Role;
use App\Http\Middleware\RequirePin;
use App\Models\Geng;
use App\Models\User;
use Database\Seeders\GengSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function buatGeng(): void
    {
        $this->seed(GengSeeder::class);
    }

    protected function geng(int $urutan): Geng
    {
        return Geng::query()->where('urutan', $urutan)->firstOrFail();
    }

    /** Pengurus yang sudah login, punya PIN, dan PIN-nya sudah diverifikasi di sesi ini. */
    protected function masukSebagaiPengurus(Role $role = Role::TataTertib): User
    {
        $user = User::factory()->role($role)->denganPin('123456')->create();
        $this->actingAs($user)->withSession([RequirePin::SESSION_KEY => now()->getTimestamp()]);

        return $user;
    }
}
