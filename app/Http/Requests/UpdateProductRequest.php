<?php

namespace App\Http\Requests;

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
        return $this->productRules($this->route('product'));
    }
}
