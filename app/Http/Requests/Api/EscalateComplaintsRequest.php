<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Action;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscalateComplaintsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'complaint_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'complaint_ids.*' => ['integer', 'exists:complaints,id'],
            'filters' => ['sometimes', 'array'],
            'filters.borough' => ['sometimes', 'string'],
            'filters.type' => ['sometimes', 'string'],
            'filters.status' => ['sometimes', 'string'],
            'filters.risk_level' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high'])],
            'reason' => ['required', 'string', 'max:500'],
            'escalation_level' => ['required', 'string', Rule::in(['manager', 'supervisor', 'emergency'])],
            'send_notification' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'complaint_ids.max' => 'Maximum of 100 complaints can be escalated at once',
            'complaint_ids.*.exists' => 'One or more complaint IDs do not exist',
            'reason.required' => 'Escalation reason is required',
            'reason.max' => 'Escalation reason cannot exceed 500 characters',
            'escalation_level.required' => 'Escalation level is required',
            'escalation_level.in' => 'Escalation level must be: manager, supervisor, or emergency',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Must provide either complaint_ids or filters
            if (!$this->has('complaint_ids') && !$this->has('filters')) {
                $validator->errors()->add(
                    'complaint_ids',
                    'Either complaint_ids or filters must be provided'
                );
            }
        });
    }
}