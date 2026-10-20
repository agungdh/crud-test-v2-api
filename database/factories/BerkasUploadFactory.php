<?php

namespace Database\Factories;

use App\Models\BerkasUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BerkasUpload>
 */
class BerkasUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'object_key' => 'upload/'.Str::ulid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'upload_url_expires_at' => now()->addMinutes(15),
            'purge_at' => now()->addDay(),
        ];
    }
}
