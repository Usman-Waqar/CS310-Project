<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vendor */
class VendorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     default_tap_price: string,
     *     default_tap_price_formatted: string,
     *     icon_name: string
     * }
     */
    public function toArray(Request $request): array
    {
        $code = $this->codeValue();
        $defaultTapPrice = Money::normalize((string) $this->default_tap_price);

        return [
            'id' => $this->id,
            'code' => $code,
            'name' => $this->name,
            'category' => $this->category,
            'default_tap_price' => $defaultTapPrice,
            'default_tap_price_formatted' => Money::formatTry($defaultTapPrice),
            'icon_name' => $this->icon_name,
        ];
    }
}
