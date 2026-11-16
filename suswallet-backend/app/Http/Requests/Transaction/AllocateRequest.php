<?php

namespace App\Http\Requests\Transaction;

use App\Enums\VendorCode;
use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateRequest extends FormRequest
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
            'vendor_code' => ['required_without:destination_wallet_id', 'nullable', Rule::enum(VendorCode::class)],
            'destination_wallet_id' => ['required_without:vendor_code', 'nullable', 'uuid'],
            'amount' => $this->amountRules('0.01', '50000'),
            'idempotency_key' => $this->idempotencyKeyRules(),
        ];
    }
}
