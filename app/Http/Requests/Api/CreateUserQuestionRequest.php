<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserQuestionRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:3', 'max:1000'],
            'conversation_id' => ['sometimes', 'string', 'uuid'],
            'context' => ['sometimes', 'array'],
            'context.current_page' => ['sometimes', 'string'],
            'context.user_location' => ['sometimes', 'string'],
            'context.filters_applied' => ['sometimes', 'array'],
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
            'question.required' => 'Question is required',
            'question.min' => 'Question must be at least 3 characters',
            'question.max' => 'Question cannot exceed 1000 characters',
            'conversation_id.uuid' => 'Conversation ID must be a valid UUID',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Generate conversation ID if not provided
        if (!$this->has('conversation_id')) {
            $this->merge([
                'conversation_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }
    }
}