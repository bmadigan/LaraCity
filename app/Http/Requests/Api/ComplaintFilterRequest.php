<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintFilterRequest extends FormRequest
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
            'borough' => ['sometimes', 'string', Rule::in(Complaint::getBoroughs())],
            'type' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(Complaint::getStatuses())],
            'priority' => ['sometimes', 'string', Rule::in(Complaint::getPriorities())],
            'agency' => ['sometimes', 'string', 'max:10'],
            'risk_level' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high'])],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort_by' => ['sometimes', 'string', Rule::in(['submitted_at', 'priority', 'risk_score', 'complaint_type'])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
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
            'borough.in' => 'Borough must be one of: ' . implode(', ', Complaint::getBoroughs()),
            'status.in' => 'Status must be one of: ' . implode(', ', Complaint::getStatuses()),
            'priority.in' => 'Priority must be one of: ' . implode(', ', Complaint::getPriorities()),
            'risk_level.in' => 'Risk level must be one of: low, medium, high',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
            'per_page.max' => 'Maximum of 100 records per page allowed',
        ];
    }

    /**
     * Get validated data with defaults
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->safe([
            'borough',
            'type',
            'status', 
            'priority',
            'agency',
            'risk_level',
            'date_from',
            'date_to',
        ]);
    }

    /**
     * Get pagination parameters
     *
     * @return array<string, mixed>
     */
    public function getPagination(): array
    {
        return [
            'per_page' => $this->input('per_page', 15),
            'page' => $this->input('page', 1),
        ];
    }

    /**
     * Get sorting parameters
     *
     * @return array<string, mixed>
     */
    public function getSorting(): array
    {
        return [
            'sort_by' => $this->input('sort_by', 'submitted_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
        ];
    }
}