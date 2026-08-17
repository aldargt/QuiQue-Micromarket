<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('sale'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => preg_replace('/\s+/u', ' ', trim((string) $this->input('reason')))]);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
