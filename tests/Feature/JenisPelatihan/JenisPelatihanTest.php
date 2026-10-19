<?php

use App\Models\Diklat;
use App\Models\JenisPelatihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function jenisPelatihanActor(): User
{
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}

it('requires authentication', function () {
    $this->getJson('/api/jenis-pelatihan')->assertUnauthorized();
    $this->postJson('/api/jenis-pelatihan', [])->assertUnauthorized();
    $this->putJson('/api/jenis-pelatihan/'.Str::uuid(), [])->assertUnauthorized();
    $this->deleteJson('/api/jenis-pelatihan/'.Str::uuid())->assertUnauthorized();
});

it('creates a jenis pelatihan for any authenticated user', function () {
    $actor = jenisPelatihanActor();

    $response = $this->postJson('/api/jenis-pelatihan', ['jenis_pelatihan' => 'Sertifikasi']);

    $response->assertCreated()
        ->assertJsonPath('data.jenis_pelatihan', 'Sertifikasi')
        ->assertJsonMissingPath('data.id')
        ->assertJsonStructure(['data' => ['uuid', 'jenis_pelatihan']]);

    $jenisPelatihan = JenisPelatihan::query()->firstOrFail();

    expect(Str::isUuid($jenisPelatihan->uuid))->toBeTrue()
        ->and((int) $jenisPelatihan->created_by)->toBe((int) $actor->id);
});

it('validates the jenis pelatihan payload', function () {
    jenisPelatihanActor();

    $this->postJson('/api/jenis-pelatihan', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');

    $this->postJson('/api/jenis-pelatihan', ['jenis_pelatihan' => Str::random(256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');
});

it('rejects a duplicate name case-insensitively', function () {
    jenisPelatihanActor();

    JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Sertifikasi']);

    $this->postJson('/api/jenis-pelatihan', ['jenis_pelatihan' => 'sertifikasi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');
});

it('lists jenis pelatihan with search and pagination', function () {
    jenisPelatihanActor();

    JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Alpha']);
    JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Beta']);
    JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Gamma']);

    $this->getJson('/api/jenis-pelatihan')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.jenis_pelatihan', 'Alpha');

    $this->getJson('/api/jenis-pelatihan?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/jenis-pelatihan?search=bet')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.jenis_pelatihan', 'Beta');
});

it('shows a jenis pelatihan by uuid', function () {
    jenisPelatihanActor();

    $jenisPelatihan = JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Sertifikasi']);

    $this->getJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $jenisPelatihan->uuid)
        ->assertJsonPath('data.jenis_pelatihan', 'Sertifikasi')
        ->assertJsonMissingPath('data.id');

    $this->getJson('/api/jenis-pelatihan/'.Str::uuid())->assertNotFound();
});

it('replaces the name on update', function () {
    $actor = jenisPelatihanActor();

    $jenisPelatihan = JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Lama']);

    $this->putJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}", ['jenis_pelatihan' => 'Baru'])
        ->assertOk()
        ->assertJsonPath('data.jenis_pelatihan', 'Baru');

    $fresh = $jenisPelatihan->fresh();

    expect($fresh->jenis_pelatihan)->toBe('Baru')
        ->and((int) $fresh->updated_by)->toBe((int) $actor->id);

    $this->putJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');
});

it('rejects a duplicate name owned by another jenis pelatihan on update', function () {
    jenisPelatihanActor();

    JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Sertifikasi']);

    $jenisPelatihan = JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Lain']);

    $this->putJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}", ['jenis_pelatihan' => 'SERTIFIKASI'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');

    $this->putJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}", ['jenis_pelatihan' => 'Lain'])
        ->assertOk();
});

it('does not register a patch route', function () {
    jenisPelatihanActor();

    $jenisPelatihan = JenisPelatihan::factory()->create();

    $this->patchJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}", [])->assertMethodNotAllowed();
});

it('soft deletes a jenis pelatihan and frees its name', function () {
    $actor = jenisPelatihanActor();

    $jenisPelatihan = JenisPelatihan::factory()->create(['jenis_pelatihan' => 'Sertifikasi']);

    $this->deleteJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}")->assertNoContent();

    expect($jenisPelatihan->fresh()->trashed())->toBeTrue()
        ->and((int) $jenisPelatihan->fresh()->deleted_by)->toBe((int) $actor->id);

    $this->getJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}")->assertNotFound();

    $this->postJson('/api/jenis-pelatihan', ['jenis_pelatihan' => 'Sertifikasi'])
        ->assertCreated();
});

it('refuses to delete a jenis pelatihan that is still used by a diklat', function () {
    jenisPelatihanActor();

    $jenisPelatihan = JenisPelatihan::factory()->create();
    $diklat = Diklat::factory()->create(['jenis_pelatihan_id' => $jenisPelatihan->id]);

    $this->deleteJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jenis_pelatihan');

    expect($jenisPelatihan->fresh()->trashed())->toBeFalse();

    $diklat->delete();

    $this->deleteJson("/api/jenis-pelatihan/{$jenisPelatihan->uuid}")->assertNoContent();
});
