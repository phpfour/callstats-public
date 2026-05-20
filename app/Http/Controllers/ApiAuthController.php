<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ApiAuthController extends Controller
{
    /**
     * Handle Agent login and return an access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check((string) $request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->hasRole(UserRole::AGENT->value)) {
            return response()->json(['message' => 'User is not an Agent'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only('id', 'name', 'email', 'created_at', 'updated_at'),
        ]);
    }
}
