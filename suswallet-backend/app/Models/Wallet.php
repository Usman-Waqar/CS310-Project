<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasUuids;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $balance = Money::normalize((string) $this->balance);

        return [
            'id' => $this->id,
            'is_main' => $this->is_main,
            'balance' => $balance,
            'balance_formatted' => Money::formatTry($balance),
            'currency' => $this->currency,
            'vendor' => $this->vendor ? [
                'id' => $this->vendor->id,
                'code' => $this->vendor->code,
                'name' => $this->vendor->name,
                'category' => $this->vendor->category,
                'default_tap_price' => Money::normalize((string) $this->vendor->default_tap_price),
                'default_tap_price_formatted' => Money::formatTry((string) $this->vendor->default_tap_price),
                'icon_name' => $this->vendor->icon_name,
            ] : null,
        ];
    }
}
