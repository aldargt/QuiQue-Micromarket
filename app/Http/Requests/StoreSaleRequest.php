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
        $this->merge([
            'items' => $items,
            'customer_full_name' => preg_replace('/\s+/u', ' ', trim((string) $this->customer_full_name)) ?: null,
            'customer_phone' => preg_replace('/[^0-9+]/', '', (string) $this->customer_phone) ?: null,
            'customer_ci' => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $this->customer_ci))) ?: null,
        ]);
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
            'raffle_decision' => ['nullable', Rule::in(['participate', 'decline'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('branch_id', $this->user()->branch_id)],
            'customer_full_name' => [Rule::requiredIf($this->raffle_decision === 'participate' && ! $this->filled('customer_id')), 'nullable', 'string', 'max:150'],
            'customer_phone' => [Rule::requiredIf($this->raffle_decision === 'participate' && ! $this->filled('customer_id')), 'nullable', 'string', 'min:6', 'max:30', Rule::unique('customers', 'phone')->where('branch_id', $this->user()->branch_id)],
            'customer_ci' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'ci')->where('branch_id', $this->user()->branch_id)],
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
