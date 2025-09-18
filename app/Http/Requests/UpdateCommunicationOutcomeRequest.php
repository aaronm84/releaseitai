<?php

namespace App\Http\Requests;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationOutcomeRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'outcome_summary' => 'required|string',
            'follow_up_actions' => 'nullable|array',
            'follow_up_due_date' => 'nullable|date|after_or_equal:today',
            'status' => ['nullable', Rule::in(Communication::STATUSES)],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'outcome_summary.required' => 'An outcome summary is required.',
            'follow_up_due_date.after_or_equal' => 'Follow-up due date must be today or in the future.',
        ];
    }
}