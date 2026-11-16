<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money;

trait NormalizesSusWalletInput
{
    /**
     * @return list<string>
     */
    protected function sabanciEmailRules(): array
    {
        return ['required', 'email', 'regex:/^[^@]+@sabanciuniv\.edu$/i'];
    }

    /**
     * @return array<string, string>
     */
    protected function sabanciEmailMessages(): array
    {
        return [
            'email.regex' => 'Only @sabanciuniv.edu accounts are supported.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function idempotencyKeyRules(): array
    {
        return ['required', 'string', 'max:64'];
    }

    /**
     * @return list<string>
     */
    protected function newPasswordRules(): array
    {
        return ['required', 'string', 'min:8', 'confirmed'];
    }

    /**
     * @return list<string>
     */
    protected function amountRules(string $min = '0.01', string $max = '5000', bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', 'decimal:0,2', "min:{$min}", "max:{$max}"];
    }

    protected function normalizeEmailInput(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => strtolower(trim($email)),
            ]);
        }
    }

    protected function normalizeVendorCodeInput(): void
    {
        $vendorCode = $this->input('vendor_code');

        if (is_string($vendorCode) && trim($vendorCode) !== '') {
            $this->merge([
                'vendor_code' => strtoupper(trim($vendorCode)),
            ]);
        }
    }

    public function normalizedEmail(): string
    {
        return strtolower(trim((string) $this->validated('email')));
    }

    public function normalizedStudentId(): string
    {
        return trim((string) $this->validated('student_id'));
    }

    public function normalizedAmount(): string
    {
        return Money::normalize((string) $this->validated('amount'));
    }

    public function nullableNormalizedAmount(): ?string
    {
        $amount = $this->validated('amount');

        return $amount !== null ? Money::normalize((string) $amount) : null;
    }
}
