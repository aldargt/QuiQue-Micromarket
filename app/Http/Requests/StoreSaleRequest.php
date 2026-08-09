<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->map(fn ($item) => ['product_id' => $item['product_id'] ?? null, 'quantity' => $item['quantity'] ?? null])
            ->values()->all();
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'lte:999999999.999'],
            'payment_type' => ['required', Rule::in(['cash', 'qr', 'mixed'])],
            'cash_received' => ['nullable', 'decimal:0,2', 'gte:0'],
            'cash_amount' => ['nullable', 'decimal:0,2', 'gte:0'],
            'qr_amount' => ['nullable', 'decimal:0,2', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agregue al menos un producto a la venta.',
            'items.min' => 'Agregue al menos un producto a la venta.',
            'items.*.product_id.distinct' => 'Un producto no puede aparecer más de una vez.',
            'items.*.quantity.decimal' => 'La cantidad admite como máximo tres decimales.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}
