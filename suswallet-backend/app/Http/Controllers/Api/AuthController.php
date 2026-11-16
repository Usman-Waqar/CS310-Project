<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->normalizedEmail())->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->validated('password'), $user->password)) {
            return ErrorCode::InvalidCredentials->toResponse();
        }

        $token = $user->createToken($request->deviceName())->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserProfileResource($user))->resolve($request),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new UserProfileResource($request->user()))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update(['password' => $request->validated('password')]);
        $user->revokeOtherTokens();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->normalizedEmail())
            ->where('student_id', $request->normalizedStudentId())
            ->active()
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'No active student account matches the provided email and Student ID.',
                'errors' => [
                    'email' => ['No active student account matches the provided email and Student ID.'],
                ],
            ], 422);
        }

        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password has been reset. You can now log in with your new password.',
        ]);
    }
}
