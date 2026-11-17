<?php

namespace Tests;

use App\Enums\Role;
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

    /** Pengurus yang sudah login. */
    protected function masukSebagaiPengurus(Role $role = Role::TataTertib): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }
}
