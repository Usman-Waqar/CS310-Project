<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Completed = 'COMPLETED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
