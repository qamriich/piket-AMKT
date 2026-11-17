<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
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

    public function denganPin(string $pin = '123456'): static
    {
        return $this->afterCreating(fn ($user) => $user->setPin($pin));
    }
}
