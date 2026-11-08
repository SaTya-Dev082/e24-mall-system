<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
            'role' => 'customer',
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->createToken($user, $data['device_name'] ?? 'api-client'),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($data['email']))])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active' || $user->deleted_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->createToken($user, $data['device_name'] ?? 'api-client'),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function createToken(User $user, string $deviceName): string
    {
        $abilities = $user->role === 'admin' ? ['admin', 'customer'] : ['customer'];

        return $user->createToken($deviceName, $abilities)->plainTextToken;
    }
}
