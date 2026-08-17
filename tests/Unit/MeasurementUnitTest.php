<?php

namespace Tests\Unit;

use App\Enums\MeasurementUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeasurementUnitTest extends TestCase
{
    #[DataProvider('quantities')]
    public function test_quantity_format_is_exact(MeasurementUnit $unit, string $quantity, string $expected): void
    {
        $this->assertSame($expected, $unit->formatQuantity($quantity));
    }

    public static function quantities(): array
    {
        return [
            'one unit' => [MeasurementUnit::Unit, '1.000', '1 unidad'],
            'four units' => [MeasurementUnit::Unit, '4.000', '4 unidades'],
            'kilogram below one' => [MeasurementUnit::Kilogram, '0.500', '0,500 kg'],
            'one kilogram' => [MeasurementUnit::Kilogram, '1.000', '1,000 kg'],
            'kilograms' => [MeasurementUnit::Kilogram, '46.500', '46,500 kg'],
            'liter below one' => [MeasurementUnit::Liter, '0.500', '0,500 L'],
            'one liter' => [MeasurementUnit::Liter, '1.000', '1,000 L'],
            'liters' => [MeasurementUnit::Liter, '2.500', '2,500 L'],
        ];
    }

    public function test_input_quantity_format_matches_commercial_unit(): void
    {
        $this->assertSame('10', MeasurementUnit::Unit->formatInputQuantity('10.000'));
        $this->assertSame('10.5', MeasurementUnit::Kilogram->formatInputQuantity('10.500'));
        $this->assertSame('0.125', MeasurementUnit::Liter->formatInputQuantity('0.125'));
    }
}
