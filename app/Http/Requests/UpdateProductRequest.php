<?php

namespace App\Http\Requests;

use App\Enums\RoleSlug;
use App\Http\Requests\Concerns\ValidatesProductData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductData;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProductData();
    }

    public function rules(): array
    {
        if ($this->user()->hasAnyRole([RoleSlug::Cashier->value])) {
            return [
                'purchase_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
                'sale_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
                'minimum_stock' => ['required', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
                'expires_at' => ['nullable', 'date_format:Y-m-d'],
            ];
        }

        return $this->productRules($this->route('product'));
    }
}
