<?php

namespace App\Http\Resources;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletFilter;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly WalletFilter|string $walletFilter = WalletFilter::All,
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     status: string,
     *     amount: string,
     *     amount_formatted: string,
     *     sign: string,
     *     vendor_code: string,
     *     title: ?string,
     *     subtitle: ?string,
     *     is_refunded: bool,
     *     source_wallet_id: ?string,
     *     destination_wallet_id: ?string,
     *     idempotency_key: string,
     *     metadata: array<string, mixed>,
     *     created_at: ?string
     * }
     */
    public function toArray(Request $request): array
    {
        $vendorCode = $this->metadata['vendor_code']
            ?? $this->sourceWallet?->vendor?->codeValue()
            ?? $this->destinationWallet?->vendor?->codeValue()
            ?? WalletFilter::Main->value;

        $type = $this->type instanceof TransactionType
            ? $this->type
            : TransactionType::from((string) $this->type);

        $status = $this->status instanceof TransactionStatus
            ? $this->status->value
            : (string) $this->status;

        $sign = $type->resolveSign($this->walletFilter);
        $amount = Money::normalize((string) $this->amount);

        return [
            'id' => $this->id,
            'type' => $type->value,
            'status' => $status,
            'amount' => $amount,
            'amount_formatted' => Money::formatTry($amount, $sign),
            'sign' => $sign,
            'vendor_code' => $vendorCode,
            'title' => $this->description,
            'subtitle' => $this->metadata['subtitle'] ?? null,
            'is_refunded' => $this->is_refunded,
            'source_wallet_id' => $this->source_wallet_id,
            'destination_wallet_id' => $this->destination_wallet_id,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
