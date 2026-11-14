<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->toProfileArray(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\.\./'],
        ]);

        $user = $request->user();
        $updates = [];

        if (array_key_exists('full_name', $validated)) {
            $updates['full_name'] = trim($validated['full_name']);
        }

        if (array_key_exists('phone', $validated)) {
            $updates['phone'] = $validated['phone'] !== null ? trim($validated['phone']) : null;
        }

        if ($request->hasFile('avatar')) {
            $this->deleteStoredAvatar($user->avatar_path, $user->id);

            $storedPath = $request->file('avatar')->store('avatars', 'public');
            $updates['avatar_path'] = '/storage/'.$storedPath;
        } elseif (array_key_exists('avatar_path', $validated)) {
            if ($validated['avatar_path'] !== $user->avatar_path) {
                $this->deleteStoredAvatar($user->avatar_path, $user->id);
            }

            $updates['avatar_path'] = $validated['avatar_path'];
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $user->fresh()->toProfileArray(),
        ]);
    }

    private function deleteStoredAvatar(?string $avatarPath, string $userId): void
    {
        if ($avatarPath && str_starts_with($avatarPath, '/storage/avatars/')) {
            $stillReferenced = User::where('id', '!=', $userId)
                ->where('avatar_path', $avatarPath)
                ->exists();

            if (! $stillReferenced) {
                $oldRelative = substr($avatarPath, strlen('/storage/'));
                Storage::disk('public')->delete($oldRelative);
            }
        }
    }
}
