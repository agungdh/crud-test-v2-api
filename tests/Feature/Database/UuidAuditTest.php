<?php

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

it('assigns a uuid v4 to every new model', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->uuid))->toBeTrue()
        ->and(Uuid::fromString($user->uuid)->getVersion())->toBe(4);
});

it('uses the uuid as the route key', function () {
    expect((new User)->getRouteKeyName())->toBe('uuid');
});

it('fills the audit columns from the authenticated actor', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor);

    $user = User::factory()->create();

    expect($user->created_by)->toBe($actor->id)
        ->and($user->updated_by)->toBe($actor->id)
        ->and($user->deleted_by)->toBeNull();
});

it('updates the audit columns when the model changes', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $user = User::factory()->create();
    $user->update(['email' => 'updated@example.com']);

    expect($user->updated_by)->toBe($actor->id);
});

it('records who soft deleted and restored the model', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $user = User::factory()->create();
    $user->delete();

    expect($user->deleted_at)->not->toBeNull()
        ->and($user->deleted_by)->toBe($actor->id);

    $user->restore();

    expect($user->deleted_at)->toBeNull()
        ->and($user->deleted_by)->toBeNull();
});

it('hides the audit columns from model serialization', function () {
    $user = User::factory()->create();
    $pegawai = Pegawai::factory()->create(['user_id' => $user->id]);

    $auditColumns = [
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    expect($user->toArray())->not->toHaveKeys($auditColumns)
        ->and($pegawai->toArray())->not->toHaveKeys($auditColumns)
        ->and($user->toArray())->not->toHaveKey('password');
});
