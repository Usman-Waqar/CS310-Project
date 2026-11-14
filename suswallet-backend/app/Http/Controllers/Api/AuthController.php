<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'regex:/^[^@]+@sabanciuniv\.edu$/i'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email.regex' => 'Only @sabanciuniv.edu accounts are supported.',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid university email or password.',
            ], 401);
        }

        $token = $user->createToken($validated['device_name'] ?? 'suswallet-mobile')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->toProfileArray(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->toProfileArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update(['password' => $validated['password']]);

        $currentToken = $user->currentAccessToken();

        if ($currentToken && isset($currentToken->id)) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'regex:/^[^@]+@sabanciuniv\.edu$/i'],
            'student_id' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.regex' => 'Only @sabanciuniv.edu accounts are supported.',
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))
            ->where('student_id', trim($validated['student_id']))
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'No active student account matches the provided email and Student ID.',
                'errors' => [
                    'email' => ['No active student account matches the provided email and Student ID.'],
                ],
            ], 422);
        }

        $user->update(['password' => $validated['password']]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password has been reset. You can now log in with your new password.',
        ]);
    }
}
