<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

            foreach (User::pluck('id') as $userId) {
                Wallet::firstOrCreate(
                    ['user_id' => $userId, 'vendor_id' => $vendor->id],
                    ['is_main' => false, 'balance' => '0.00', 'currency' => 'TRY']
                );
            }
        });
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}
