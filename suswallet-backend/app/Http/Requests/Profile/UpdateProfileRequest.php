<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\.\./'],
        ];
    }

    /**
     * @return array{full_name?: string, phone?: ?string}
     */
    public function sanitizedAttributes(): array
    {
        $validated = $this->validated();
        $attributes = [];

        if (array_key_exists('full_name', $validated)) {
            $attributes['full_name'] = trim((string) $validated['full_name']);
        }

        if (array_key_exists('phone', $validated)) {
            $attributes['phone'] = $validated['phone'] !== null ? trim((string) $validated['phone']) : null;
        }

        return $attributes;
    }
}
