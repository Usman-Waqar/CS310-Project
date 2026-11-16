<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
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
    public function toArray(Request $request): array
    {
        $balance = Money::normalize((string) $this->balance);

        return [
            'id' => $this->id,
            'is_main' => $this->is_main,
            'balance' => $balance,
            'balance_formatted' => Money::formatTry($balance),
            'currency' => $this->currency,
            'vendor' => $this->vendor ? (new VendorResource($this->vendor))->resolve($request) : null,
        ];
    }
}
