<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     student_id: string,
     *     su_username: string,
     *     email: string,
     *     full_name: string,
     *     initials: string,
     *     phone: ?string,
     *     masked_phone: ?string,
     *     avatar_path: ?string,
     *     avatar_url: ?string,
     *     is_active: bool,
     *     sucard_status: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'su_username' => $this->su_username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'initials' => $this->initials(),
            'phone' => $this->phone,
            'masked_phone' => $this->maskedPhone(),
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'sucard_status' => $this->is_active ? 'Active' : 'Inactive',
        ];
    }
}
