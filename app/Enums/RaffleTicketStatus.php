<?php

namespace App\Enums;

enum RaffleTicketStatus: string
{
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo', self::Expired => 'Expirado'
        };
    }
}
