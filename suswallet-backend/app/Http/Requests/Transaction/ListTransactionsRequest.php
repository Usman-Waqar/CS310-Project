<?php

namespace App\Http\Requests\Transaction;

use App\Enums\WalletFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $wallet = $this->query('wallet');

        if (is_string($wallet)) {
            $this->merge([
                'wallet' => strtoupper(trim($wallet)),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'wallet' => ['nullable', Rule::enum(WalletFilter::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function walletFilter(): WalletFilter
    {
        return WalletFilter::resolve($this->validated('wallet'));
    }

    public function limit(): ?int
    {
        $limit = $this->validated('limit');

        return $limit !== null ? (int) $limit : null;
    }
}
