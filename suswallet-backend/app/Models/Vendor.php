<?php

namespace App\Models;

use App\Enums\VendorCode;
use App\Services\WalletService;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'category',
    'default_tap_price',
    'icon_name',
    'is_active',
])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => VendorCode::class,
            'default_tap_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Vendor $vendor): void {
            if (! $vendor->is_active) {
                return;
            }

            app(WalletService::class)->provisionServiceWalletsForVendor($vendor);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function seedDefaults(): void
    {
        foreach (VendorCode::seedAll() as $vendorData) {
            self::updateOrCreate(['code' => $vendorData['code']], $vendorData);
        }
    }

    public function codeValue(): string
    {
        return $this->code instanceof VendorCode ? $this->code->value : (string) $this->code;
    }

    public function sortOrder(): int
    {
        if ($this->code instanceof VendorCode) {
            return $this->code->sortOrder();
        }

        return is_string($this->code)
            ? (VendorCode::tryFrom($this->code)?->sortOrder() ?? 99)
            : 99;
    }

    public function defaultTapTitle(): string
    {
        if ($this->code instanceof VendorCode) {
            return $this->code->defaultTapTitle();
        }

        return (is_string($this->code) ? VendorCode::tryFrom($this->code)?->defaultTapTitle() : null)
            ?? $this->name.' tap';
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}
