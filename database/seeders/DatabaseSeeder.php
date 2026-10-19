<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Pegawai::factory(10)->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'username' => '198001012005011001',
        ]);

        Pegawai::factory()->create([
            'user_id' => $user->id,
            'nip' => '198001012005011001',
            'nama' => 'Test User',
        ]);
    }
}
