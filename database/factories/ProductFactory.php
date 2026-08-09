<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'category_id' => Category::factory()->state(['branch_id' => $branch]),
            'barcode' => null,
            'internal_code' => fn (array $attributes) => $attributes['barcode'] === null
                ? 'PRD-'.Str::upper(Str::random(12))
                : null,
            'name' => fake()->words(3, true),
            'unit' => MeasurementUnit::Unit,
            'purchase_price' => fake()->randomFloat(2, 0, 100),
            'sale_price' => fake()->randomFloat(2, 0, 150),
            'stock' => '0.000',
            'minimum_stock' => fake()->randomFloat(3, 0, 10),
            'expires_at' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
