<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'user_id' => User::factory(), 'sale_number' => fake()->unique()->bothify('VTA-????-######'), 'subtotal' => '10.00', 'total' => '10.00', 'status' => SaleStatus::Confirmed, 'confirmed_at' => now()];
    }
}
