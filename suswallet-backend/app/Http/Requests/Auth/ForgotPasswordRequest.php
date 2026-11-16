<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    use NormalizesSusWalletInput;

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
            'student_id' => ['required', 'string', 'max:20'],
            'password' => $this->newPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->sabanciEmailMessages();
    }
}
