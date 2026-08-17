<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case PositiveAdjustment = 'positive_adjustment';
    case NegativeAdjustment = 'negative_adjustment';
    case SaleReversal = 'sale_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Salida',
            self::PositiveAdjustment => 'Ajuste positivo',
            self::NegativeAdjustment => 'Ajuste negativo',
            self::SaleReversal => 'Reversión de venta',
        };
    }

    public function increasesStock(): bool
    {
        return in_array($this, [self::Entry, self::PositiveAdjustment, self::SaleReversal], true);
    }
}
