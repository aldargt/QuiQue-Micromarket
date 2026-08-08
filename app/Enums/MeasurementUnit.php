<?php

namespace App\Enums;

enum MeasurementUnit: string
{
    case Unit = 'unit';
    case Kilogram = 'kilogram';
    case Gram = 'gram';
    case Liter = 'liter';
    case Milliliter = 'milliliter';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidad',
            self::Kilogram => 'Kilogramo',
            self::Gram => 'Gramo',
            self::Liter => 'Litro',
            self::Milliliter => 'Mililitro',
        };
    }
}
