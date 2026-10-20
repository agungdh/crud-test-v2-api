<?php

use App\Models\BerkasUpload;
use App\Models\Diklat;
use App\Models\JenisPelatihan;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function diklatPayload(JenisPelatihan $jenisPelatihan, array $overrides = []): array
{
    return array_merge([
        'jenis_pelatihan_uuid' => $jenisPelatihan->uuid,
        'nomor_surat' => 'Diklat-001',
        'materi_pengembangan' => 'Materi Pengembangan',
        'dari_tanggal_pelaksanaan' => '2026-01-05',
        'sampai_tanggal_pelaksanaan' => '2026-01-07',
        'jumlah_jam_pelatihan' => 24,
    ], $overrides);
}

function diklatActor(): User
{
    $user = User::factory()->create();
    Pegawai::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    return $user;
}

function diklatFor(User $actor, array $attributes = []): Diklat
{
    return Diklat::factory()->create([
        'pegawai_id' => $actor->pegawai->id,
        ...$attributes,
    ]);
}

/**
 * @return array<int, string>
 */
function diklatAuditColumns(): array
{
    return [
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}

/**
 * Collect every key in a decoded JSON payload, including nested ones.
 *
 * @param  array<array-key, mixed>  $payload
 * @return array<int, string>
 */
function diklatPayloadKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        $keys[] = (string) $key;

        if (is_array($value)) {
            $keys = [...$keys, ...diklatPayloadKeys($value)];
        }
    }

    return $keys;
}

function fakeTemporaryBerkasUrls(): void
{
    Storage::fake('s3');
    Storage::fake('s3-temp');

    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration, array $options): string => "https://files.test/{$path}",
    );
}

function berkasUploadFor(User $actor, string $filename = 'sertifikat.pdf', string $contents = '%PDF-1.4 test'): BerkasUpload
{
    $upload = BerkasUpload::factory()->create([
        'user_id' => $actor->id,
        'object_key' => 'upload/'.Str::ulid().'.'.pathinfo($filename, PATHINFO_EXTENSION),
        'original_filename' => $filename,
    ]);

    Storage::disk('s3-temp')->put($upload->object_key, $contents);

    return $upload;
}

it('requires authentication', function () {
    $this->getJson('/api/diklat')->assertUnauthorized();
    $this->postJson('/api/diklat', [])->assertUnauthorized();
    $this->putJson('/api/diklat/'.Str::uuid(), [])->assertUnauthorized();
    $this->deleteJson('/api/diklat/'.Str::uuid())->assertUnauthorized();
});

it('forbids users without a pegawai profile', function () {
    Sanctum::actingAs(User::factory()->create());

    $jenisPelatihan = JenisPelatihan::factory()->create();

    $this->getJson('/api/diklat')->assertForbidden();
    $this->postJson('/api/diklat', diklatPayload($jenisPelatihan))->assertForbidden();
});

it('creates a diklat owned by the authenticated pegawai', function () {
    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Sertifikasi']);
    $foreignPegawai = Pegawai::factory()->create();

    $response = $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'pegawai_uuid' => $foreignPegawai->uuid,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.nomor_surat', 'Diklat-001')
        ->assertJsonPath('data.materi_pengembangan', 'Materi Pengembangan')
        ->assertJsonPath('data.jenis_pelatihan.uuid', $jenisPelatihan->uuid)
        ->assertJsonPath('data.jenis_pelatihan.jenis_pelatihan', 'Sertifikasi')
        ->assertJsonPath('data.dari_tanggal_pelaksanaan', '2026-01-05')
        ->assertJsonPath('data.sampai_tanggal_pelaksanaan', '2026-01-07')
        ->assertJsonPath('data.jumlah_jam_pelatihan', 24)
        ->assertJsonPath('data.pegawai_uuid', $actor->pegawai->uuid)
        ->assertJsonPath('data.berkas_filename', null)
        ->assertJsonPath('data.berkas_url', null)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.pegawai_id')
        ->assertJsonMissingPath('data.jenis_pelatihan_id')
        ->assertJsonStructure([
            'data' => [
                'uuid', 'pegawai_uuid', 'nomor_surat', 'materi_pengembangan',
                'dari_tanggal_pelaksanaan', 'sampai_tanggal_pelaksanaan',
                'jumlah_jam_pelatihan', 'berkas_filename', 'berkas_url',
                'jenis_pelatihan' => ['uuid', 'jenis_pelatihan'],
            ],
        ]);

    $diklat = Diklat::query()->firstOrFail();

    expect(Str::isUuid($diklat->uuid))->toBeTrue()
        ->and((int) $diklat->pegawai_id)->toBe((int) $actor->pegawai->id)
        ->and((int) $diklat->jenis_pelatihan_id)->toBe((int) $jenisPelatihan->id)
        ->and((int) $diklat->created_by)->toBe((int) $actor->id);
});

it('validates the diklat payload', function () {
    diklatActor();

    $this->postJson('/api/diklat', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'jenis_pelatihan_uuid',
            'nomor_surat',
            'materi_pengembangan',
            'dari_tanggal_pelaksanaan',
            'sampai_tanggal_pelaksanaan',
            'jumlah_jam_pelatihan',
        ]);

    $jenisPelatihan = JenisPelatihan::factory()->create();

    $this->postJson('/api/diklat', diklatPayload($jenisPelatihan, [
        'dari_tanggal_pelaksanaan' => '2026-02-10',
        'sampai_tanggal_pelaksanaan' => '2026-02-01',
        'jumlah_jam_pelatihan' => 0,
        'berkas_uuid' => 'not-a-uuid',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'dari_tanggal_pelaksanaan',
            'sampai_tanggal_pelaksanaan',
            'jumlah_jam_pelatihan',
            'berkas_uuid',
        ]);

    $this->postJson('/api/diklat', diklatPayload($jenisPelatihan, [
        'jenis_pelatihan_uuid' => Str::uuid(),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan_uuid');

    $trashed = JenisPelatihan::factory()->create();
    $trashed->delete();

    $this->postJson('/api/diklat', diklatPayload($trashed))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan_uuid');
});

it('lists only the diklat of the authenticated pegawai with filters', function () {
    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $otherJenisPelatihan = JenisPelatihan::factory()->create();

    diklatFor($actor, [
        'jenis_pelatihan_id' => $jenisPelatihan->id,
        'nomor_surat' => 'Diklat-Alpha',
        'materi_pengembangan' => 'Manajemen Risiko',
        'dari_tanggal_pelaksanaan' => '2026-01-05',
        'sampai_tanggal_pelaksanaan' => '2026-01-07',
    ]);

    diklatFor($actor, [
        'jenis_pelatihan_id' => $jenisPelatihan->id,
        'nomor_surat' => 'Diklat-Beta',
        'materi_pengembangan' => 'Akuntansi Dasar',
        'dari_tanggal_pelaksanaan' => '2026-03-10',
        'sampai_tanggal_pelaksanaan' => '2026-03-12',
    ]);

    diklatFor($actor, [
        'jenis_pelatihan_id' => $otherJenisPelatihan->id,
        'nomor_surat' => 'Diklat-Gamma',
        'materi_pengembangan' => 'Audit Internal',
        'dari_tanggal_pelaksanaan' => '2026-06-01',
        'sampai_tanggal_pelaksanaan' => '2026-06-02',
    ]);

    Diklat::factory()->create([
        'nomor_surat' => 'Diklat-Foreign',
        'materi_pengembangan' => 'Rahasia',
    ]);

    $this->getJson('/api/diklat')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Gamma')
        ->assertJsonPath('data.2.nomor_surat', 'Diklat-Alpha');

    $this->getJson('/api/diklat?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/diklat?search=diklat-beta')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Beta');

    $this->getJson('/api/diklat?search=audit')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Gamma');

    $this->getJson("/api/diklat?jenis_pelatihan_uuid={$jenisPelatihan->uuid}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/diklat?dari_tanggal=2026-03-01')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Gamma');

    $this->getJson('/api/diklat?sampai_tanggal=2026-03-01')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Alpha');

    $this->getJson('/api/diklat?dari_tanggal=2026-03-01&sampai_tanggal=2026-03-31')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nomor_surat', 'Diklat-Beta');
});

it('shows an owned diklat by uuid', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor, ['nomor_surat' => 'Diklat-Milik']);

    $this->getJson("/api/diklat/{$diklat->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $diklat->uuid)
        ->assertJsonPath('data.nomor_surat', 'Diklat-Milik')
        ->assertJsonPath('data.jenis_pelatihan.uuid', $diklat->jenisPelatihan->uuid)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.pegawai_id');
});

it('hides diklat of another pegawai behind a 404', function () {
    $actor = diklatActor();
    $foreign = Diklat::factory()->create();

    $this->getJson("/api/diklat/{$foreign->uuid}")->assertNotFound();
    $this->putJson("/api/diklat/{$foreign->uuid}", diklatPayload($foreign->jenisPelatihan))->assertNotFound();
    $this->deleteJson("/api/diklat/{$foreign->uuid}")->assertNotFound();
    $this->getJson('/api/diklat/'.Str::uuid())->assertNotFound();

    expect(Diklat::query()->whereKey($foreign->id)->exists())->toBeTrue()
        ->and((int) $actor->pegawai->diklat()->count())->toBe(0);
});

it('replaces every field on update', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor, ['nomor_surat' => 'Diklat-Lama']);
    $jenisPelatihan = JenisPelatihan::factory()->create();

    $this->putJson("/api/diklat/{$diklat->uuid}", [
        'jenis_pelatihan_uuid' => $jenisPelatihan->uuid,
        'nomor_surat' => 'Diklat-Baru',
        'materi_pengembangan' => 'Materi Baru',
        'dari_tanggal_pelaksanaan' => '2026-05-01',
        'sampai_tanggal_pelaksanaan' => '2026-05-02',
        'jumlah_jam_pelatihan' => 8,
    ])
        ->assertOk()
        ->assertJsonPath('data.nomor_surat', 'Diklat-Baru')
        ->assertJsonPath('data.materi_pengembangan', 'Materi Baru')
        ->assertJsonPath('data.jenis_pelatihan.uuid', $jenisPelatihan->uuid)
        ->assertJsonPath('data.dari_tanggal_pelaksanaan', '2026-05-01')
        ->assertJsonPath('data.jumlah_jam_pelatihan', 8);

    $fresh = $diklat->fresh();

    expect($fresh->nomor_surat)->toBe('Diklat-Baru')
        ->and((int) $fresh->jenis_pelatihan_id)->toBe((int) $jenisPelatihan->id)
        ->and((int) $fresh->updated_by)->toBe((int) $actor->id);
});

it('requires every field on a full replacement', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor);

    $this->putJson("/api/diklat/{$diklat->uuid}", ['nomor_surat' => 'Hanya Nomor Surat'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'jenis_pelatihan_uuid',
            'materi_pengembangan',
            'dari_tanggal_pelaksanaan',
            'sampai_tanggal_pelaksanaan',
            'jumlah_jam_pelatihan',
        ]);
});

it('does not register a patch route', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor);

    $this->patchJson("/api/diklat/{$diklat->uuid}", [])->assertMethodNotAllowed();
});

it('soft deletes an owned diklat', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor);

    $this->deleteJson("/api/diklat/{$diklat->uuid}")->assertNoContent();

    expect($diklat->fresh()->trashed())->toBeTrue()
        ->and((int) $diklat->fresh()->deleted_by)->toBe((int) $actor->id);

    $this->getJson("/api/diklat/{$diklat->uuid}")->assertNotFound();
});

it('never exposes the audit columns', function () {
    $actor = diklatActor();
    $diklat = diklatFor($actor);
    $jenisPelatihan = JenisPelatihan::factory()->create();

    $responses = [
        $this->getJson('/api/diklat')->assertOk(),
        $this->getJson("/api/diklat/{$diklat->uuid}")->assertOk(),
        $this->postJson('/api/diklat', diklatPayload($jenisPelatihan, ['nomor_surat' => 'Diklat-Audit']))->assertCreated(),
        $this->putJson("/api/diklat/{$diklat->uuid}", diklatPayload($jenisPelatihan, ['nomor_surat' => 'Diklat-Put']))->assertOk(),
    ];

    foreach ($responses as $response) {
        expect(array_intersect(diklatPayloadKeys($response->json()), diklatAuditColumns()))->toBeEmpty();
    }
});

it('attaches a presigned upload and promotes it to the permanent bucket', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $upload = berkasUploadFor($actor);

    $response = $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.berkas_filename', 'sertifikat.pdf');

    $diklat = Diklat::query()->firstOrFail();

    expect($diklat->berkas_path)->toStartWith("diklat/{$diklat->uuid}/")
        ->and($response->json('data.berkas_url'))->toBe("https://files.test/{$diklat->berkas_path}");

    Storage::disk('s3')->assertExists($diklat->berkas_path);
    Storage::disk('s3-temp')->assertMissing($upload->object_key);

    $upload->refresh();

    expect($upload->consumed_at)->not->toBeNull()
        ->and($upload->mime_type)->toBe('application/pdf');
});

it('keeps the stored attachment when the update omits it', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $diklat = diklatFor($actor, ['jenis_pelatihan_id' => $jenisPelatihan->id]);
    $path = "diklat/{$diklat->uuid}/lama.pdf";

    Storage::disk('s3')->put($path, 'lama');

    $diklat->update(['berkas_path' => $path, 'berkas_filename' => 'lama.pdf']);

    $this->putJson("/api/diklat/{$diklat->uuid}", diklatPayload($jenisPelatihan))
        ->assertOk()
        ->assertJsonPath('data.berkas_filename', 'lama.pdf');

    expect($diklat->fresh()->berkas_path)->toBe($path);

    Storage::disk('s3')->assertExists($path);
});

it('replaces the stored attachment and removes the previous object', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $diklat = diklatFor($actor, ['jenis_pelatihan_id' => $jenisPelatihan->id]);
    $previousPath = "diklat/{$diklat->uuid}/lama.pdf";

    Storage::disk('s3')->put($previousPath, 'lama');

    $diklat->update(['berkas_path' => $previousPath, 'berkas_filename' => 'lama.pdf']);
    $upload = berkasUploadFor($actor, 'baru.pdf');

    $this->putJson("/api/diklat/{$diklat->uuid}", [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ])->assertOk()
        ->assertJsonPath('data.berkas_filename', 'baru.pdf');

    $fresh = $diklat->fresh();

    expect($fresh->berkas_path)->not->toBe($previousPath)
        ->and($fresh->berkas_filename)->toBe('baru.pdf');

    Storage::disk('s3')->assertMissing($previousPath);
    Storage::disk('s3')->assertExists($fresh->berkas_path);
    Storage::disk('s3-temp')->assertMissing($upload->object_key);

    expect($upload->fresh()->consumed_at)->not->toBeNull();
});

it('removes the stored attachment when berkas_uuid is null', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $diklat = diklatFor($actor, ['jenis_pelatihan_id' => $jenisPelatihan->id]);
    $path = "diklat/{$diklat->uuid}/lama.pdf";

    Storage::disk('s3')->put($path, 'lama');

    $diklat->update(['berkas_path' => $path, 'berkas_filename' => 'lama.pdf']);

    $this->putJson("/api/diklat/{$diklat->uuid}", [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.berkas_filename', null)
        ->assertJsonPath('data.berkas_url', null);

    expect($diklat->fresh()->berkas_path)->toBeNull();

    Storage::disk('s3')->assertMissing($path);
});

it('removes the attachment object when the diklat is deleted', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $diklat = diklatFor($actor);
    $path = "diklat/{$diklat->uuid}/lama.pdf";

    Storage::disk('s3')->put($path, 'lama');

    $diklat->update(['berkas_path' => $path, 'berkas_filename' => 'lama.pdf']);

    $this->deleteJson("/api/diklat/{$diklat->uuid}")->assertNoContent();

    Storage::disk('s3')->assertMissing($path);
});

it('rejects an upload that belongs to another user', function () {
    fakeTemporaryBerkasUrls();

    diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $upload = berkasUploadFor(User::factory()->create());

    $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas_uuid');

    expect(Diklat::query()->count())->toBe(0)
        ->and($upload->fresh()->consumed_at)->toBeNull();
});

it('rejects an upload whose object was never uploaded', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $upload = BerkasUpload::factory()->create(['user_id' => $actor->id]);

    $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas_uuid');

    expect(Diklat::query()->count())->toBe(0)
        ->and($upload->fresh()->consumed_at)->toBeNull();
});

it('rejects an upload with an unsupported file type', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $upload = berkasUploadFor($actor, 'sertifikat.pdf', 'plain text');

    $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas_uuid');

    expect(Diklat::query()->count())->toBe(0)
        ->and($upload->fresh()->consumed_at)->toBeNull();
});

it('does not allow an upload to be consumed twice', function () {
    fakeTemporaryBerkasUrls();

    $actor = diklatActor();
    $jenisPelatihan = JenisPelatihan::factory()->create();
    $upload = berkasUploadFor($actor);

    $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan),
        'berkas_uuid' => $upload->uuid,
    ])->assertCreated();

    $this->postJson('/api/diklat', [
        ...diklatPayload($jenisPelatihan, ['nomor_surat' => 'Diklat-002']),
        'berkas_uuid' => $upload->uuid,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas_uuid');

    expect(Diklat::query()->count())->toBe(1);
});
