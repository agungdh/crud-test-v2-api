<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * Verify the given credentials and return the matching user.
     *
     * @param  array{login: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function attemptLogin(array $credentials): User
    {
        $identifier = trim($credentials['login']);

        $user = User::query()->where('email', $identifier)->first()
            ?? User::query()->where('username', $identifier)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        return $user;
    }

    /**
     * Issue a new bearer token for the given user.
     */
    public function createToken(User $user, ?string $deviceName = null): string
    {
        return $user->createToken($deviceName ?? 'mobile')->plainTextToken;
    }

    /**
     * Revoke the access token used for the current request, if any.
     */
    public function revokeCurrentToken(User $user): void
    {
        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
    }
}
