<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Qr => 'QR',
        };
    }
}
