<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'initiated_by_user_id',
    'source_wallet_id',
    'destination_wallet_id',
    'type',
    'amount',
    'status',
    'idempotency_key',
    'is_refunded',
    'description',
    'metadata',
])]
class Transaction extends Model
{
    use HasUuids;

    public const TYPE_DEPOSIT = 'DEPOSIT';

    public const TYPE_ALLOCATION = 'ALLOCATION';

    public const TYPE_CARD_TAP = 'CARD_TAP';

    public const TYPE_REFUND = 'REFUND';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_refunded' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(string $walletFilter = 'ALL'): array
    {
        $vendorCode = $this->metadata['vendor_code']
            ?? $this->sourceWallet?->vendor?->code
            ?? $this->destinationWallet?->vendor?->code
            ?? 'MAIN';

        $normalizedFilter = strtoupper($walletFilter);

        $sign = match ($this->type) {
            self::TYPE_DEPOSIT, self::TYPE_REFUND => '+',
            self::TYPE_CARD_TAP => '-',
            self::TYPE_ALLOCATION => match ($normalizedFilter) {
                'MAIN' => '-',
                'SHUTTLE', 'CAFETERIA', 'PRINTING' => '+',
                default => '',
            },
            default => '',
        };

        $amount = Money::normalize((string) $this->amount);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
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
