<?php

namespace App\Http\Requests;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCommunicationRequest extends FormRequest
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
            'channel' => ['nullable', Rule::in(Communication::CHANNELS)],
            'type' => ['nullable', Rule::in(Communication::TYPES)],
            'priority' => ['nullable', Rule::in(Communication::PRIORITIES)],
            'direction' => ['nullable', Rule::in(Communication::DIRECTIONS)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'thread_id' => 'nullable|string',
            'sensitive_only' => 'sometimes|in:true,false,1,0',
            'participant_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:communication_date,priority,channel,type',
            'sort_direction' => 'nullable|in:asc,desc',
        ];
    }
}