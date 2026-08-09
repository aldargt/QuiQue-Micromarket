<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return 'Confirmada';
    }
}
