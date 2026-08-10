<?php

namespace App\Enums;

enum MeasurementUnit: string
{
    case Unit = 'unit';
    case Kilogram = 'kilogram';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidad',
            self::Kilogram => 'Kilogramo',
            self::Liter => 'Litro',
        };
    }

    public function formatQuantity(string|float|int $quantity): string
    {
        return match ($this) {
            self::Unit => number_format((float) $quantity, 0, ',', '.').' '.(bccomp((string) $quantity, '1', 3) === 0 ? 'unidad' : 'unidades'),
            self::Kilogram => number_format((float) $quantity, 3, ',', '.').' kg',
            self::Liter => number_format((float) $quantity, 3, ',', '.').' L',
        };
    }
}
