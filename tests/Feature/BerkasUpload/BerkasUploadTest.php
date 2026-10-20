<?php

use App\Models\BerkasUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    $this->deleteJson('/api/berkas-upload/'.Str::uuid())->assertUnauthorized();
});

it('creates a one-time presigned upload url', function () {
    fakeBerkasUploadUrls();

    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/berkas-upload', ['filename' => 'sertifikat.pdf']);

    $response->assertCreated()
        ->assertJsonPath('data.filename', 'sertifikat.pdf')
        ->assertJsonPath('data.headers.If-None-Match', '*')
        ->assertJsonStructure([
            'data' => ['uuid', 'filename', 'upload_url', 'headers', 'expires_at'],
        ])
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.object_key')
        ->assertJsonMissingPath('data.created_at');

    $upload = BerkasUpload::query()->firstOrFail();

    expect(Str::isUuid($upload->uuid))->toBeTrue()
        ->and((int) $upload->user_id)->toBe((int) $actor->id)
        ->and($upload->object_key)->toStartWith('upload/')
        ->and($upload->object_key)->toEndWith('.pdf')
        ->and($upload->consumed_at)->toBeNull()
        ->and($upload->upload_url_expires_at->isFuture())->toBeTrue()
        ->and($upload->purge_at->isFuture())->toBeTrue()
        ->and($response->json('data.headers'))->not->toHaveKey('Host')
        ->and($response->json('data.upload_url'))->toBe("https://temp.test/{$upload->object_key}");
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

it('cancels a pending upload and removes the temporary object', function () {
    fakeBerkasUploadUrls();

    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $upload = BerkasUpload::factory()->create(['user_id' => $actor->id]);
    Storage::disk('s3-temp')->put($upload->object_key, 'isi');

    $this->deleteJson("/api/berkas-upload/{$upload->uuid}")->assertNoContent();

    Storage::disk('s3-temp')->assertMissing($upload->object_key);

    expect($upload->fresh()->trashed())->toBeTrue();
});

it('hides uploads of another user behind a 404', function () {
    fakeBerkasUploadUrls();

    Sanctum::actingAs(User::factory()->create());

    $foreign = BerkasUpload::factory()->create();

    $this->deleteJson("/api/berkas-upload/{$foreign->uuid}")->assertNotFound();
    $this->deleteJson('/api/berkas-upload/'.Str::uuid())->assertNotFound();

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('does not cancel an upload that has already been consumed', function () {
    fakeBerkasUploadUrls();

    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $upload = BerkasUpload::factory()->create([
        'user_id' => $actor->id,
        'consumed_at' => now(),
    ]);

    $this->deleteJson("/api/berkas-upload/{$upload->uuid}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas_uuid');

    expect($upload->fresh()->trashed())->toBeFalse();
});

it('prunes expired temporary uploads', function () {
    fakeBerkasUploadUrls();

    $upload = BerkasUpload::factory()->create([
        'purge_at' => now()->subMinute(),
    ]);

    Storage::disk('s3-temp')->put($upload->object_key, 'isi');

    $this->artisan('berkas-upload:prune')->assertSuccessful();

    Storage::disk('s3-temp')->assertMissing($upload->object_key);

    expect($upload->fresh()->trashed())->toBeTrue();
});
