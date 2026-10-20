<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
        //
    }

    /**
     * Authenticate the user via a stateful session or issue a bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->attemptLogin($request->safe()->only(['login', 'password']));

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return response()->json([
                'user' => UserResource::make($user),
            ]);
        }

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $this->authService->createToken($user, $request->validated('device_name')),
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revoke the current token or end the stateful session.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->revokeCurrentToken($request->user());

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make(
            $this->authService->profile($request->user()),
        );
    }
}
