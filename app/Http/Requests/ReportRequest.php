<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['today', 'yesterday', 'date', 'range', 'month'])],
            'date' => ['required_if:period,date', 'nullable', 'date_format:Y-m-d'],
            'start' => ['required_if:period,range', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_if:period,range', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'month' => ['required_if:period,month', 'nullable', 'date_format:Y-m'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date' => 'fecha',
            'start' => 'fecha inicial',
            'end' => 'fecha final',
            'month' => 'mes',
        ];
    }
}
