<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Body: { username, pin, device_name? } -> { token, user }
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::customers()->where('username', strtolower(trim($data['username'])))->first();

        // Pesan sama untuk user tidak ada / PIN salah, agar tidak membocorkan user ID yang valid.
        if (! $user || ! Hash::check($data['pin'], $user->pin)) {
            return response()->json(['message' => __('User ID atau PIN salah.')], 401);
        }

        $token = $user->createToken($data['device_name'] ?? 'lumina-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout — mencabut token yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Berhasil keluar.')]);
    }
}
