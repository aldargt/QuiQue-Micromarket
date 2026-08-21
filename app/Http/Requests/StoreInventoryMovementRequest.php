<?php

namespace App\Http\Requests;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [InventoryMovement::class, $this->route('product')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => preg_replace('/\s+/u', ' ', trim((string) $this->input('reason'))),
            'observation' => $this->filled('observation') ? trim((string) $this->input('observation')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_map(
                fn (InventoryMovementType $type) => $type->value,
                InventoryMovementType::manualCases(),
            ))],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'reason' => ['required', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
