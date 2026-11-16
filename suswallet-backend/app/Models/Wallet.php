<?php

namespace App\Models;

use App\Enums\VendorCode;
use App\Http\Resources\WalletResource;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'vendor_id',
    'is_main',
    'balance',
    'currency',
])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, HasUuids;

    public const CURRENCY_TRY = 'TRY';

    public const DEFAULT_BALANCE = '0.00';

    /**
     * @return array{is_main: bool, balance: string, currency: string}
     */
    public static function defaultAttributes(bool $isMain = false, string $balance = self::DEFAULT_BALANCE): array
    {
        return [
            'is_main' => $isMain,
            'balance' => $balance,
            'currency' => self::CURRENCY_TRY,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'balance' => 'decimal:2',
        ];
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    public function scopeService(Builder $query): Builder
    {
        return $query->where('is_main', false);
    }

    public function scopeForVendorCode(Builder $query, VendorCode|string $vendorCode): Builder
    {
        $code = $vendorCode instanceof VendorCode ? $vendorCode->value : $vendorCode;

        return $query->whereHas('vendor', static fn (Builder $q) => $q->where('code', $code));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return array{
     *     id: string,
     *     is_main: bool,
     *     balance: string,
     *     balance_formatted: string,
     *     currency: string,
     *     vendor: ?array{
     *         id: string,
     *         code: string,
     *         name: string,
     *         category: string,
     *         default_tap_price: string,
     *         default_tap_price_formatted: string,
     *         icon_name: string
     *     }
     * }
     */
    public function toApiArray(): array
    {
        return (new WalletResource($this))->resolve();
    }
}
