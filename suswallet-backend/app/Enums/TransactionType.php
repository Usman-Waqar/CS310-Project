<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'DEPOSIT';
    case Allocation = 'ALLOCATION';
    case CardTap = 'CARD_TAP';
    case Refund = 'REFUND';

    public function resolveSign(WalletFilter|string $filter = WalletFilter::All): string
    {
        $resolvedFilter = WalletFilter::resolve($filter);

        return match ($this) {
            self::Deposit, self::Refund => '+',
            self::CardTap => '-',
            self::Allocation => match ($resolvedFilter) {
                WalletFilter::Main => '-',
                WalletFilter::Shuttle, WalletFilter::Cafeteria, WalletFilter::Printing => '+',
                WalletFilter::All => '',
            },
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
