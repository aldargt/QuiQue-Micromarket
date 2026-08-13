<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptRaffleParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => preg_replace('/\s+/u', ' ', trim((string) $this->full_name)) ?: null,
            'phone' => preg_replace('/[^0-9+]/', '', (string) $this->phone) ?: null,
            'ci' => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $this->ci))) ?: null,
        ]);
    }

    public function rules(): array
    {
        $new = ! $this->filled('customer_id');
        $branchId = $this->user()->branch_id;

        return [
            'customer_id' => ['nullable', 'integer'],
            'full_name' => [Rule::requiredIf($new), 'nullable', 'string', 'max:150'],
            'phone' => [Rule::requiredIf($new), 'nullable', 'string', 'min:6', 'max:30', Rule::unique('customers')->where('branch_id', $branchId)],
            'ci' => ['nullable', 'string', 'max:30', Rule::unique('customers')->where('branch_id', $branchId)],
        ];
    }
}
