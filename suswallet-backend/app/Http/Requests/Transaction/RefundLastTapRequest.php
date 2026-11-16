<?php

namespace App\Http\Requests\Transaction;

use App\Enums\VendorCode;
use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundLastTapRequest extends FormRequest
{
    use NormalizesSusWalletInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeVendorCodeInput();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKeyRules(),
            'vendor_code' => ['nullable', Rule::enum(VendorCode::class)],
        ];
    }
}
