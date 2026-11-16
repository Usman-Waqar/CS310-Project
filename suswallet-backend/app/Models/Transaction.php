<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletFilter;
use App\Http\Resources\TransactionResource;
use App\Support\Money;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUuids;

    public const TYPE_DEPOSIT = TransactionType::Deposit->value;

    public const TYPE_ALLOCATION = TransactionType::Allocation->value;

    public const TYPE_CARD_TAP = TransactionType::CardTap->value;

    public const TYPE_REFUND = TransactionType::Refund->value;

    public const STATUS_COMPLETED = TransactionStatus::Completed->value;

    /**
     * @return array{vendor_code: string, subtitle: string}
     */
    public static function depositMetadata(): array
    {
        return [
            'vendor_code' => WalletFilter::Main->value,
            'subtitle' => 'Simulated top-up',
        ];
    }

    /**
     * @return array{vendor_code: string, vendor_name: string, subtitle: string}
     */
    public static function allocationMetadata(?Vendor $vendor): array
    {
        $vendorName = $vendor?->name ?? 'Service';

        return [
            'vendor_code' => $vendor?->codeValue() ?? 'SERVICE',
            'vendor_name' => $vendorName,
            'subtitle' => 'Main → '.$vendorName,
        ];
    }

    /**
     * @return array{vendor_code: string, vendor_name: string, subtitle: string}
     */
    public static function cardTapMetadata(?Vendor $vendor): array
    {
        $vendorName = $vendor?->name ?? 'Service';

        return [
            'vendor_code' => $vendor?->codeValue() ?? 'SERVICE',
            'vendor_name' => $vendorName,
            'subtitle' => 'Card tap · '.$vendorName,
        ];
    }

    /**
     * @return array{
     *     vendor_code: string,
     *     vendor_name: string,
     *     requested_vendor_code: ?string,
     *     refunded_transaction_id: string,
     *     subtitle: string
     * }
     */
    public static function refundMetadata(
        self $refundedTap,
        ?Vendor $vendor = null,
        ?string $requestedVendorCode = null
    ): array {
        $vendorCode = $vendor
            ? $vendor->codeValue()
            : (string) ($refundedTap->metadata['vendor_code'] ?? 'SERVICE');
        $vendorName = $vendor?->name ?? (string) ($refundedTap->metadata['vendor_name'] ?? 'Service');

        return [
            'vendor_code' => $vendorCode,
            'vendor_name' => $vendorName,
            'requested_vendor_code' => $requestedVendorCode,
            'refunded_transaction_id' => $refundedTap->id,
            'subtitle' => 'Refunded '.$refundedTap->description,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'is_refunded' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeTouchingWallet(Builder $query, string $walletId): Builder
    {
        return $query->where(function (Builder $sub) use ($walletId): void {
            $sub->where('source_wallet_id', $walletId)
                ->orWhere('destination_wallet_id', $walletId);
        });
    }

    public function scopeUnrefundedTaps(Builder $query): Builder
    {
        return $query->where('type', TransactionType::CardTap)
            ->where('is_refunded', false);
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
     * Verify whether an existing transaction matches a retried idempotent request payload.
     */
    public function matchesIdempotentPayload(
        TransactionType|string $expectedType,
        ?string $expectedAmount = null,
        ?string $expectedSourceWalletId = null,
        ?string $expectedDestinationWalletId = null,
        ?string $expectedVendorCode = null,
        ?string $expectedDescription = null,
    ): bool {
        $expectedTypeValue = $expectedType instanceof TransactionType ? $expectedType->value : $expectedType;
        $actualTypeValue = $this->type instanceof TransactionType ? $this->type->value : (string) $this->type;

        if ($actualTypeValue !== $expectedTypeValue) {
            return false;
        }

        if ($expectedAmount !== null && Money::comp((string) $this->amount, $expectedAmount) !== 0) {
            return false;
        }

        if ($expectedTypeValue === self::TYPE_REFUND) {
            $metadata = $this->metadata ?? [];
            $actualVendor = array_key_exists('requested_vendor_code', $metadata)
                ? $metadata['requested_vendor_code']
                : ($metadata['vendor_code'] ?? null);

            return $actualVendor === $expectedVendorCode;
        }

        return $this->source_wallet_id === $expectedSourceWalletId
            && $this->destination_wallet_id === $expectedDestinationWalletId
            && ($expectedDescription === null || $this->description === $expectedDescription);
    }

    /**
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
    public function toApiArray(WalletFilter|string $walletFilter = WalletFilter::All): array
    {
        return (new TransactionResource($this, $walletFilter))->resolve();
    }
}
