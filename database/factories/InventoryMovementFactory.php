<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'sale_id' => null,
            'type' => InventoryMovementType::Entry,
            'quantity' => '1.000',
            'stock_before' => '0.000',
            'stock_after' => '1.000',
            'reason' => 'Movimiento de prueba',
            'observation' => null,
        ];
    }
}
