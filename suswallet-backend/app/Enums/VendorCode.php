<?php

namespace App\Enums;

enum VendorCode: string
{
    case Shuttle = 'SHUTTLE';
    case Cafeteria = 'CAFETERIA';
    case Printing = 'PRINTING';

    public function label(): string
    {
        return match ($this) {
            self::Shuttle => 'Shuttle',
            self::Cafeteria => 'Cafeteria',
            self::Printing => 'Printing',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Shuttle => 'TRANSPORT',
            self::Cafeteria => 'DINING',
            self::Printing => 'PRINT',
        };
    }

    public function defaultTapPrice(): string
    {
        return match ($this) {
            self::Shuttle => '21.50',
            self::Cafeteria => '96.00',
            self::Printing => '8.00',
        };
    }

    public function iconName(): string
    {
        return match ($this) {
            self::Shuttle => 'directions_bus',
            self::Cafeteria => 'restaurant',
            self::Printing => 'print',
        };
    }

    public function defaultTapTitle(): string
    {
        return match ($this) {
            self::Shuttle => 'Shuttle fare',
            self::Cafeteria => 'Cafeteria',
            self::Printing => 'Print job',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Shuttle => 1,
            self::Cafeteria => 2,
            self::Printing => 3,
        };
    }

    /**
     * @return array{code: string, name: string, category: string, default_tap_price: string, icon_name: string, is_active: bool}
     */
    public function seedAttributes(): array
    {
        return [
            'code' => $this->value,
            'name' => $this->label(),
            'category' => $this->category(),
            'default_tap_price' => $this->defaultTapPrice(),
            'icon_name' => $this->iconName(),
            'is_active' => true,
        ];
    }

    /**
     * @return list<array{code: string, name: string, category: string, default_tap_price: string, icon_name: string, is_active: bool}>
     */
    public static function seedAll(): array
    {
        return array_map(
            static fn (self $code): array => $code->seedAttributes(),
            self::cases()
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
