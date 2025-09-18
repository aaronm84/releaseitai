<?php

namespace App\Http\Requests;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCommunicationRequest extends FormRequest
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
            'query' => 'required|string|min:3',
            'release_id' => 'nullable|exists:releases,id',
            'channel' => ['nullable', Rule::in(Communication::CHANNELS)],
            'type' => ['nullable', Rule::in(Communication::TYPES)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'query.required' => 'A search query is required.',
            'query.min' => 'Search query must be at least 3 characters long.',
            'release_id.exists' => 'The selected release does not exist.',
        ];
    }
}