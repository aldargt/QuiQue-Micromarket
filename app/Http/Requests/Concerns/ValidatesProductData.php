<?php

namespace App\Http\Requests\Concerns;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Product;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesProductData
{
    protected function prepareProductData(): void
    {
        $barcode = trim((string) $this->input('barcode'));

        $this->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name'))),
            'barcode' => $barcode === '' ? null : $barcode,
            'expires_at' => $this->input('expires_at') ?: null,
        ]);
    }

    /** @return array<string, array<mixed>> */
    protected function productRules(?Product $product = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'barcode' => [
                'nullable',
                'string',
                'regex:/^[0-9]{8,14}$/',
                $this->uniqueActiveBarcodeRule($product),
            ],
            'category_id' => ['required', 'integer', $this->validCategoryRule($product)],
            'unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'purchase_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
        ];

    }

    private function validCategoryRule(?Product $product): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($product): void {
            $category = Category::query()
                ->whereKey($value)
                ->where('branch_id', $this->user()->branch_id)
                ->first();

            $keepsHistoricalCategory = $product && $product->category_id === (int) $value;

            if (! $category || (! $category->is_active && ! $keepsHistoricalCategory)) {
                $fail('La categoría seleccionada no está disponible para este producto.');
            }
        };
    }

    private function uniqueActiveBarcodeRule(?Product $product): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($product): void {
            if ($value === null || ($product && ! $product->is_active)) {
                return;
            }

            $exists = Product::query()
                ->where('branch_id', $this->user()->branch_id)
                ->where('barcode', $value)
                ->where('is_active', true)
                ->when($product, fn ($query) => $query->whereKeyNot($product->getKey()))
                ->exists();

            if ($exists) {
                $fail('Ya existe un producto activo con este código de barras en la sucursal.');
            }
        };
    }
}
