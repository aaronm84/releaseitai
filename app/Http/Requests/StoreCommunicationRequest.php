<?php

namespace App\Http\Requests;

use App\Models\Communication;
use App\Models\CommunicationParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationRequest extends FormRequest
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
            'channel' => ['required', Rule::in(Communication::CHANNELS)],
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'communication_type' => ['required', Rule::in(Communication::TYPES)],
            'direction' => ['required', Rule::in(Communication::DIRECTIONS)],
            'priority' => ['nullable', Rule::in(Communication::PRIORITIES)],
            'communication_date' => 'nullable|date',
            'external_id' => 'nullable|string|max:255',
            'thread_id' => 'nullable|string|max:255',
            'is_sensitive' => 'nullable|boolean',
            'compliance_tags' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'attachments' => 'nullable|array',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.type' => ['nullable', Rule::in(CommunicationParticipant::PARTICIPANT_TYPES)],
            'participants.*.role' => ['nullable', Rule::in(CommunicationParticipant::ROLES)],
            'participants.*.contact_method' => 'nullable|string|max:255',
            'participants.*.metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'participants.required' => 'At least one participant is required.',
            'participants.min' => 'At least one participant is required.',
            'participants.*.user_id.required' => 'Each participant must have a valid user ID.',
            'participants.*.user_id.exists' => 'The selected user does not exist.',
        ];
    }
}