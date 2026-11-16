<?php

namespace App\Http\Requests\Transaction;

use App\Enums\VendorCode;
use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateCardTapRequest extends FormRequest
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
            'vendor_code' => ['required', Rule::enum(VendorCode::class)],
            'amount' => $this->amountRules('0.01', '5000', required: false),
            'idempotency_key' => $this->idempotencyKeyRules(),
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
