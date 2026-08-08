<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name')))]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = Category::query()
                        ->where('branch_id', $this->user()->branch_id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                        ->exists();

                    if ($exists) {
                        $fail('Ya existe una categoría con este nombre en la sucursal.');
                    }
                },
            ],
        ];
    }
}
