<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProductData;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ValidatesProductData;

    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProductData();
    }

    public function rules(): array
    {
        return $this->productRules();
    }
}
