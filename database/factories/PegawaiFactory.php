<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pegawai>
 */
class PegawaiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('##################'),
            'user_id' => fn (array $attributes): Factory => User::factory()->state([
                'username' => $attributes['nip'],
                'email' => fake()->unique()->safeEmail(),
            ]),
            'nama' => fake()->name(),
            'tanggal_lahir' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'jabatan' => fake()->jobTitle(),
            'bidang' => fake()->word(),
            'is_male' => fake()->boolean(),
            'tmt' => fake()->dateTimeBetween('-20 years', 'now')->format('Y-m-d'),
        ];
    }
}
