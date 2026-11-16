<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new UserProfileResource($request->user()))->resolve($request),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $updates = array_merge(
            $request->sanitizedAttributes(),
            $user->resolveUpdatedAvatarPath(
                avatarFile: $request->file('avatar'),
                hasAvatarPathInput: array_key_exists('avatar_path', $validated),
                avatarPathInput: $validated['avatar_path'] ?? null,
            )
        );

        if ($updates !== []) {
            $user->update($updates);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => (new UserProfileResource($user))->resolve($request),
        ]);
    }
}
