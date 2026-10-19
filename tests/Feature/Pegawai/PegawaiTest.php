<?php

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function pegawaiPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'budi@example.com',
        'password' => 'password123',
        'nip' => '198001012005011001',
        'nama' => 'Budi',
        'tanggal_lahir' => '1980-01-01',
        'jabatan' => 'Staf',
        'bidang' => 'Keuangan',
        'gender' => 'male',
        'tmt' => '2005-01-01',
    ], $overrides);
}

function authenticatePegawaiActor(): User
{
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array<int, string>
 */
function pegawaiAuditColumns(): array
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
function payloadKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        $keys[] = (string) $key;

        if (is_array($value)) {
            $keys = [...$keys, ...payloadKeys($value)];
        }
    }

    return $keys;
}

it('requires authentication', function () {
    $this->getJson('/api/pegawai')->assertUnauthorized();
    $this->postJson('/api/pegawai', pegawaiPayload())->assertUnauthorized();
    $this->putJson('/api/pegawai/'.Str::uuid(), pegawaiPayload())->assertUnauthorized();
    $this->deleteJson('/api/pegawai/'.Str::uuid())->assertUnauthorized();
});

it('creates a user and a pegawai in one request', function () {
    $actor = authenticatePegawaiActor();

    $response = $this->postJson('/api/pegawai', pegawaiPayload());

    $response->assertCreated()
        ->assertJsonPath('data.nip', '198001012005011001')
        ->assertJsonPath('data.nama', 'Budi')
        ->assertJsonPath('data.tanggal_lahir', '1980-01-01')
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.tmt', '2005-01-01')
        ->assertJsonPath('data.user.email', 'budi@example.com')
        ->assertJsonPath('data.user.username', '198001012005011001')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonStructure([
            'data' => [
                'uuid', 'nip', 'nama', 'tanggal_lahir', 'jabatan', 'bidang',
                'gender', 'tmt', 'user_uuid',
                'user' => ['uuid', 'email', 'username'],
            ],
        ]);

    $pegawai = Pegawai::query()->where('nip', '198001012005011001')->firstOrFail();

    expect(Str::isUuid($pegawai->uuid))->toBeTrue()
        ->and($response->json('data.user_uuid'))->toBe($pegawai->user->uuid)
        ->and($pegawai->user->username)->toBe($pegawai->nip)
        ->and($pegawai->user->email)->toBe('budi@example.com')
        ->and($pegawai->is_male)->toBeTrue()
        ->and(Hash::check('password123', $pegawai->user->password))->toBeTrue()
        ->and($pegawai->created_by)->toBe($actor->id);
});

it('creates a pegawai without an email', function () {
    authenticatePegawaiActor();

    $this->postJson('/api/pegawai', pegawaiPayload(['email' => null]))
        ->assertCreated()
        ->assertJsonPath('data.user.email', null);

    expect(Pegawai::query()->firstOrFail()->user->email)->toBeNull();
});

it('validates the required fields on create', function () {
    authenticatePegawaiActor();

    $this->postJson('/api/pegawai', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password', 'nip', 'nama']);
});

it('rejects a duplicate nip', function () {
    authenticatePegawaiActor();

    Pegawai::factory()->create(['nip' => '198001012005011001']);

    $this->postJson('/api/pegawai', pegawaiPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nip');
});

it('rejects a duplicate email case-insensitively', function () {
    authenticatePegawaiActor();

    $user = User::factory()->create(['email' => 'Budi@Example.com']);
    Pegawai::factory()->create(['user_id' => $user->id, 'nip' => '199001012005011002']);

    $this->postJson('/api/pegawai', pegawaiPayload(['email' => 'budi@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('validates the field formats on create', function () {
    authenticatePegawaiActor();

    $this->postJson('/api/pegawai', pegawaiPayload([
        'email' => 'not-an-email',
        'tanggal_lahir' => 'not-a-date',
        'tmt' => 'not-a-date',
        'gender' => 'not-a-gender',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'tanggal_lahir', 'tmt', 'gender']);
});

it('lists pegawai with pagination and search', function () {
    authenticatePegawaiActor();

    Pegawai::factory()->create([
        'nip' => '198001012005011001',
        'nama' => 'Budi Santoso',
        'jabatan' => 'Staf',
        'bidang' => 'Umum',
    ]);

    Pegawai::factory()->create([
        'nip' => '199001012005011002',
        'nama' => 'Siti Aminah',
        'jabatan' => 'Staf',
        'bidang' => 'Umum',
    ]);

    $ahmadUser = User::factory()->create(['email' => 'ahmad@example.com']);

    Pegawai::factory()->create([
        'user_id' => $ahmadUser->id,
        'nip' => '200001012005011003',
        'nama' => 'Ahmad',
        'jabatan' => 'Kepala Bidang',
        'bidang' => 'Keuangan',
    ]);

    $this->getJson('/api/pegawai?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/pegawai?search=1980')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Budi Santoso');

    $this->getJson('/api/pegawai?search=budi')
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Budi Santoso');

    $this->getJson('/api/pegawai?search=SITI')
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Siti Aminah');

    $this->getJson('/api/pegawai?search=Kepala')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Ahmad');

    $this->getJson('/api/pegawai?search=Keuangan')
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Ahmad');

    $this->getJson('/api/pegawai?search=ahmad@')
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Ahmad');
});

it('shows a pegawai by uuid', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create(['nama' => 'Budi']);

    $this->getJson("/api/pegawai/{$pegawai->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $pegawai->uuid)
        ->assertJsonPath('data.nama', 'Budi')
        ->assertJsonPath('data.user_uuid', $pegawai->user->uuid)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.user_id');
});

it('returns 404 for an unknown uuid', function () {
    authenticatePegawaiActor();

    $this->getJson('/api/pegawai/'.Str::uuid())->assertNotFound();
});

it('never exposes the audit columns', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create();

    $responses = [
        $this->getJson('/api/pegawai')->assertOk(),
        $this->getJson("/api/pegawai/{$pegawai->uuid}")->assertOk(),
        $this->postJson('/api/pegawai', pegawaiPayload([
            'nip' => '333333333333333333',
            'email' => 'audit-check@example.com',
        ]))->assertCreated(),
        $this->putJson("/api/pegawai/{$pegawai->uuid}", [
            'email' => $pegawai->user->email,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'tanggal_lahir' => null,
            'jabatan' => null,
            'bidang' => null,
            'gender' => null,
            'tmt' => null,
        ])->assertOk(),
    ];

    foreach ($responses as $response) {
        expect(array_intersect(payloadKeys($response->json()), pegawaiAuditColumns()))->toBeEmpty();
    }
});

it('replaces every field on update', function () {
    $actor = authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create(['nip' => '111', 'nama' => 'Old Name']);

    $this->putJson("/api/pegawai/{$pegawai->uuid}", [
        'email' => 'new@example.com',
        'password' => 'newpassword',
        'nip' => '222',
        'nama' => 'New Name',
        'tanggal_lahir' => null,
        'jabatan' => null,
        'bidang' => 'Bidang Baru',
        'gender' => 'female',
        'tmt' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.nip', '222')
        ->assertJsonPath('data.nama', 'New Name')
        ->assertJsonPath('data.tanggal_lahir', null)
        ->assertJsonPath('data.jabatan', null)
        ->assertJsonPath('data.bidang', 'Bidang Baru')
        ->assertJsonPath('data.gender', 'female')
        ->assertJsonPath('data.tmt', null)
        ->assertJsonPath('data.user.username', '222')
        ->assertJsonPath('data.user.email', 'new@example.com');

    $fresh = $pegawai->fresh();

    expect($fresh->tanggal_lahir)->toBeNull()
        ->and($fresh->jabatan)->toBeNull()
        ->and($fresh->is_male)->toBeFalse()
        ->and($fresh->user->username)->toBe('222')
        ->and(Hash::check('newpassword', $fresh->user->password))->toBeTrue()
        ->and($fresh->updated_by)->toBe($actor->id);
});

it('requires every nullable field to be present on update', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create();

    $this->putJson("/api/pegawai/{$pegawai->uuid}", [
        'nip' => $pegawai->nip,
        'nama' => $pegawai->nama,
        'jabatan' => null,
        'bidang' => null,
        'gender' => null,
        'tmt' => null,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'tanggal_lahir']);
});

it('keeps the password when it is not sent', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create();
    $oldPassword = $pegawai->user->password;

    $this->putJson("/api/pegawai/{$pegawai->uuid}", [
        'email' => $pegawai->user->email,
        'nip' => $pegawai->nip,
        'nama' => $pegawai->nama,
        'tanggal_lahir' => null,
        'jabatan' => null,
        'bidang' => null,
        'gender' => null,
        'tmt' => null,
    ])->assertOk();

    expect($pegawai->fresh()->user->password)->toBe($oldPassword);
});

it('rejects a nip or email owned by another pegawai', function () {
    authenticatePegawaiActor();

    Pegawai::factory()->create(['nip' => '111']);

    $takenUser = User::factory()->create(['email' => 'taken@example.com']);
    Pegawai::factory()->create(['user_id' => $takenUser->id, 'nip' => '333']);

    $pegawai = Pegawai::factory()->create(['nip' => '222']);

    $this->putJson("/api/pegawai/{$pegawai->uuid}", [
        'email' => 'taken@example.com',
        'nip' => '111',
        'nama' => 'Nama Baru',
        'tanggal_lahir' => null,
        'jabatan' => null,
        'bidang' => null,
        'gender' => null,
        'tmt' => null,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'nip']);
});

it('does not register a patch route', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create();

    $this->patchJson("/api/pegawai/{$pegawai->uuid}", [])->assertMethodNotAllowed();
});

it('soft deletes the pegawai and its user', function () {
    $actor = authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create();
    $user = $pegawai->user;

    $this->deleteJson("/api/pegawai/{$pegawai->uuid}")->assertNoContent();

    expect($pegawai->fresh()->trashed())->toBeTrue()
        ->and($user->fresh()->trashed())->toBeTrue()
        ->and($pegawai->fresh()->deleted_by)->toBe($actor->id)
        ->and($user->fresh()->deleted_by)->toBe($actor->id);

    $this->getJson("/api/pegawai/{$pegawai->uuid}")->assertNotFound();
});

it('allows reusing a nip and email after the previous pegawai is soft deleted', function () {
    authenticatePegawaiActor();

    $pegawai = Pegawai::factory()->create(['nip' => '999']);
    $email = $pegawai->user->email;

    $this->deleteJson("/api/pegawai/{$pegawai->uuid}")->assertNoContent();

    $this->postJson('/api/pegawai', pegawaiPayload(['nip' => '999', 'email' => $email]))
        ->assertCreated();
});

it('blocks hard deleting a user that still has a pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    expect(fn () => $pegawai->user->forceDelete())->toThrow(QueryException::class);
});

it('allows only one active pegawai per user', function () {
    $user = User::factory()->create(['username' => '777777777777777777']);

    Pegawai::factory()->create(['user_id' => $user->id, 'nip' => '777777777777777777']);

    expect(fn () => Pegawai::factory()->create(['user_id' => $user->id, 'nip' => '888888888888888888']))
        ->toThrow(QueryException::class);
});
