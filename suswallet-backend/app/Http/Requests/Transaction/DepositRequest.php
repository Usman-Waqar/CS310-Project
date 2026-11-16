<?php

namespace App\Http\Requests\Transaction;

use App\Http\Requests\Concerns\NormalizesSusWalletInput;
use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    use NormalizesSusWalletInput;

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
            'amount' => $this->amountRules('10', '5000'),
            'idempotency_key' => $this->idempotencyKeyRules(),
        ];
    }
}
