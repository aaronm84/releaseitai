<?php

namespace App\Http\Requests;

use App\Models\CommunicationParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParticipantStatusRequest extends FormRequest
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
            'delivery_status' => ['required', Rule::in(CommunicationParticipant::DELIVERY_STATUSES)],
            'response_content' => 'nullable|string',
            'response_sentiment' => ['nullable', Rule::in(CommunicationParticipant::RESPONSE_SENTIMENTS)],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'delivery_status.required' => 'A delivery status is required.',
            'delivery_status.in' => 'The selected delivery status is invalid.',
        ];
    }
}