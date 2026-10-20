<?php

use App\Models\User;
use App\Support\BerkasPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return object{options: array<string, mixed>}
 */
function fakeBerkasUploadUrls(): object
{
    $captured = new stdClass;
    $captured->options = [];

    Storage::fake('s3-temp');

    Storage::disk('s3-temp')->buildTemporaryUploadUrlsUsing(
        function (string $path, DateTimeInterface $expiration, array $options) use ($captured): array {
            $captured->options = $options;

            return [
                'url' => "https://temp.test/{$path}",
                'headers' => [
                    'Host' => ['temp.test'],
                    'If-None-Match' => ['*'],
                    'Content-Length' => [(string) ($options['ContentLength'] ?? '')],
                ],
            ];
        },
    );

    return $captured;
}

it('requires authentication', function () {
    $this->postJson('/api/berkas-upload', [])->assertUnauthorized();
});

it('creates a presigned upload url for the declared size', function () {
    $captured = fakeBerkasUploadUrls();

    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/berkas-upload', ['size' => 1024]);

    $response->assertCreated()
        ->assertJsonPath('data.max_size', 10 * 1024 * 1024)
        ->assertJsonPath('data.headers.If-None-Match', '*')
        ->assertJsonStructure([
            'data' => ['path', 'upload_url', 'headers', 'expires_at', 'max_size'],
        ])
        ->assertJsonMissingPath('data.filename')
        ->assertJsonMissingPath('data.headers.Content-Length');

    $path = $response->json('data.path');

    expect($path)->toMatch(BerkasPath::PATTERN)
        ->and(BerkasPath::epochMillis($path))->toBeGreaterThan(now()->subMinute()->getTimestampMs())
        ->and($response->json('data.upload_url'))->toBe("https://temp.test/{$path}")
        ->and($captured->options['ContentLength'])->toBe(1024);
});

it('rejects an invalid file size', function () {
    fakeBerkasUploadUrls();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/berkas-upload', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size')
        ->assertJsonPath('errors.size.0', 'The file size is required.');

    $this->postJson('/api/berkas-upload', ['size' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size');

    $this->postJson('/api/berkas-upload', ['size' => 'besar'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size');

    $this->postJson('/api/berkas-upload', ['size' => 10 * 1024 * 1024 + 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size')
        ->assertJsonPath('errors.size.0', 'The file may not be greater than 10 MB.');
});
