<?php

use App\Models\User;
use App\Support\BerkasPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function fakeBerkasUploadUrls(): void
{
    Storage::fake('s3-temp');

    Storage::disk('s3-temp')->buildTemporaryUploadUrlsUsing(
        fn (string $path, DateTimeInterface $expiration, array $options): array => [
            'url' => "https://temp.test/{$path}",
            'headers' => [
                'Host' => ['temp.test'],
                'If-None-Match' => ['*'],
            ],
        ],
    );
}

it('requires authentication', function () {
    $this->postJson('/api/berkas-upload', [])->assertUnauthorized();
});

it('creates a one-time presigned upload url', function () {
    fakeBerkasUploadUrls();

    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/berkas-upload', ['filename' => 'sertifikat.pdf']);

    $response->assertCreated()
        ->assertJsonPath('data.filename', 'sertifikat.pdf')
        ->assertJsonPath('data.headers.If-None-Match', '*')
        ->assertJsonStructure([
            'data' => ['path', 'filename', 'upload_url', 'headers', 'expires_at'],
        ])
        ->assertJsonMissingPath('data.uuid');

    $path = $response->json('data.path');

    expect($path)->toMatch(BerkasPath::PATTERN)
        ->and(BerkasPath::epochMillis($path))->toBeGreaterThan(now()->subMinute()->getTimestampMs())
        ->and($response->json('data.headers'))->not->toHaveKey('Host')
        ->and($response->json('data.upload_url'))->toBe("https://temp.test/{$path}");
});

it('rejects unsupported filenames', function () {
    fakeBerkasUploadUrls();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/berkas-upload', ['filename' => 'dokumen.exe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filename');

    $this->postJson('/api/berkas-upload', ['filename' => '../sertifikat.pdf'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filename');
});
