<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sanctum.stateful' => ['localhost:5173']]);
});

it('returns a bearer token for a valid mobile login', function () {
    $user = User::factory()->create(['email' => 'mobile@example.com']);

    $response = $this->postJson('/api/login', [
        'email' => 'mobile@example.com',
        'password' => 'password',
        'device_name' => 'android',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.uuid', $user->uuid)
        ->assertJsonMissingPath('user.id')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure([
            'user' => ['uuid', 'name', 'email'],
            'token',
            'token_type',
        ]);

    expect($response->json('token'))->not->toBeEmpty()
        ->and($user->tokens()->where('name', 'android')->exists())->toBeTrue();
});

it('does not expose the password in the login response', function () {
    User::factory()->create(['email' => 'mobile@example.com']);

    $this->postJson('/api/login', [
        'email' => 'mobile@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.id');
});

it('returns 422 when the credentials are invalid', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('returns 422 when the required credentials are missing', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('authenticates a stateful login and exposes the user via the session', function () {
    $user = User::factory()->create();

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
        ->assertJsonPath('user.uuid', $user->uuid)
        ->assertJsonMissingPath('token');

    $this->assertAuthenticatedAs($user);
    expect($user->tokens()->count())->toBe(0);

    $this->withHeader('Origin', 'http://localhost:5173')
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.uuid', $user->uuid)
        ->assertJsonMissingPath('data.id');
});

it('returns 401 when the user is not authenticated', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('returns the authenticated user for a bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.uuid', $user->uuid)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.id');
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('android')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertUnauthorized();
});

it('ends the session on a stateful logout', function () {
    $user = User::factory()->create();

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

    $this->assertAuthenticatedAs($user);

    $this->withHeader('Origin', 'http://localhost:5173')
        ->postJson('/api/logout')
        ->assertOk();

    $this->assertGuest('web');
});

it('rate limits repeated login attempts', function () {
    User::factory()->create(['email' => 'user@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
