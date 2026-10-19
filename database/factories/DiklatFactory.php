<?php

namespace Database\Factories;

use App\Models\Diklat;
use App\Models\JenisPelatihan;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diklat>
 */
class DiklatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'pegawai_id' => Pegawai::factory(),
            'jenis_pelatihan_id' => JenisPelatihan::factory(),
            'nomor_surat' => fake()->unique()->numerify('Diklat-###########'),
            'materi_pengembangan' => fake()->sentence(),
            'dari_tanggal_pelaksanaan' => $start->format('Y-m-d'),
            'sampai_tanggal_pelaksanaan' => fake()->dateTimeBetween($start, 'now')->format('Y-m-d'),
            'jumlah_jam_pelatihan' => fake()->numberBetween(1, 100),
        ];
    }
}
