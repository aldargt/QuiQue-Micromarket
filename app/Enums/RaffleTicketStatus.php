<?php

namespace App\Enums;

enum RaffleTicketStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo', self::Expired => 'Expirado', self::Cancelled => 'Anulado'
        };
    }
}
