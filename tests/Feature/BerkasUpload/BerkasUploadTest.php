<?php

use App\Models\User;
use App\Support\BerkasPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filesystems.disks.s3-temp', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'temp-bucket',
        'endpoint' => 'http://127.0.0.1:9000',
        'use_path_style_endpoint' => true,
        'upload_ttl' => 15,
        'purge_ttl' => 1440,
        'throw' => false,
        'report' => false,
    ]);

    Storage::forgetDisk('s3-temp');
});

it('requires authentication', function () {
    $this->postJson('/api/berkas-upload', [])->assertUnauthorized();
});

it('uses the requested maximum size as the s3 policy limit', function () {
    Sanctum::actingAs(User::factory()->create());

    $maxSize = 100 * 1024 * 1024;

    $response = $this->postJson('/api/berkas-upload', ['max_size' => $maxSize]);

    $response->assertCreated()
        ->assertJsonPath('data.max_size', $maxSize)
        ->assertJsonPath('data.upload_url', 'http://127.0.0.1:9000/temp-bucket')
        ->assertJsonStructure([
            'data' => ['path', 'upload_url', 'fields', 'expires_at', 'max_size'],
        ])
        ->assertJsonMissingPath('data.filename')
        ->assertJsonMissingPath('data.headers');

    $path = $response->json('data.path');
    $fields = $response->json('data.fields');
    $policy = json_decode(base64_decode($fields['Policy']), true);

    expect($path)->toMatch(BerkasPath::PATTERN)
        ->and(BerkasPath::epochMillis($path))->toBeGreaterThan(now()->subMinute()->getTimestampMs())
        ->and($fields['key'])->toBe($path)
        ->and($policy['conditions'])->toContain(['content-length-range', 1, $maxSize]);
});

it('rejects an invalid maximum file size', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/berkas-upload', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_size')
        ->assertJsonPath('errors.max_size.0', 'The maximum file size is required.');

    $this->postJson('/api/berkas-upload', ['max_size' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_size');

    $this->postJson('/api/berkas-upload', ['max_size' => 'besar'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_size');
});
