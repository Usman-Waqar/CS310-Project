<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vendor_id' => null,
            ...Wallet::defaultAttributes(true),
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vendor_id' => null,
            'is_main' => true,
        ]);
    }

    public function service(?Vendor $vendor = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'vendor_id' => $vendor?->id ?? Vendor::query()->first()?->id ?? Vendor::factory(),
            'is_main' => false,
        ]);
    }
}
