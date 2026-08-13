<?php

namespace App\Enums;

enum RaffleParticipationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente', self::Accepted => 'Aceptada', self::Declined => 'Rechazada'
        };
    }
}
