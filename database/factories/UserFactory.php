<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'nama' => fake()->firstNameFemale().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'no_kamar' => (string) fake()->numberBetween(1, 20),
            'geng_id' => null,
            'role' => Role::Warga,
            'wajib_diperiksa' => false,
            'aktif' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function pengurus(): static
    {
        return $this->role(Role::TataTertib);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }

    /** Akun baru yang belum membuat password (belum bisa login). */
    public function tanpaPassword(): static
    {
        return $this->state(fn () => ['password' => null, 'email_verified_at' => null]);
    }
}
