<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use NormalizesSusWalletInput;

    public const DEFAULT_DEVICE_NAME = 'suswallet-mobile';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmailInput();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => $this->sabanciEmailRules(),
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->sabanciEmailMessages();
    }

    public function deviceName(): string
    {
        return (string) ($this->validated('device_name') ?: self::DEFAULT_DEVICE_NAME);
    }
}
