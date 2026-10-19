<?php

namespace Database\Factories;

use App\Models\JenisPelatihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisPelatihan>
 */
class JenisPelatihanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_pelatihan' => fake()->unique()->words(2, true),
        ];
    }
}
