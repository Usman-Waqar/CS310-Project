<?php

namespace App\Enums;

enum WalletFilter: string
{
    case All = 'ALL';
    case Main = 'MAIN';
    case Shuttle = 'SHUTTLE';
    case Cafeteria = 'CAFETERIA';
    case Printing = 'PRINTING';

    public static function resolve(self|string|null $filter): self
    {
        if ($filter instanceof self) {
            return $filter;
        }

        return is_string($filter)
            ? (self::tryFrom(strtoupper(trim($filter))) ?? self::All)
            : self::All;
    }

    public function vendorCode(): ?VendorCode
    {
        return match ($this) {
            self::Shuttle => VendorCode::Shuttle,
            self::Cafeteria => VendorCode::Cafeteria,
            self::Printing => VendorCode::Printing,
            self::All, self::Main => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
