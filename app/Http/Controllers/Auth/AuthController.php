<?php

namespace App\Http\Controllers\Auth;

use App\Application\User\AuthenticateUser;
use App\Application\User\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->safe()->only(['name', 'email', 'password']));
        $token = $user->createToken($request->string('device_name')->value() ?: 'api')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $credentials = $request->validated();
        $authentication = $authenticateUser->handle(
            $credentials['email'],
            $credentials['password'],
            $credentials['device_name'] ?? 'api',
        );

        return response()->json([
            'message' => 'Authenticated successfully.',
            'user' => new UserResource($authentication['user']),
            'token' => $authentication['token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
