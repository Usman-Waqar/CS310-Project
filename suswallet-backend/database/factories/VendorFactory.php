<?php

namespace Database\Factories;

use App\Enums\VendorCode;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return VendorCode::Shuttle->seedAttributes();
    }

    public function shuttle(): static
    {
        return $this->state(fn (array $attributes): array => VendorCode::Shuttle->seedAttributes());
    }

    public function cafeteria(): static
    {
        return $this->state(fn (array $attributes): array => VendorCode::Cafeteria->seedAttributes());
    }

    public function printing(): static
    {
        return $this->state(fn (array $attributes): array => VendorCode::Printing->seedAttributes());
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
