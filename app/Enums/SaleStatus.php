<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Anulada',
        };
    }
}
