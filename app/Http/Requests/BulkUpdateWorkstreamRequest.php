<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateWorkstreamRequest extends FormRequest
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
            'workstream_ids' => 'required|array',
            'workstream_ids.*' => 'exists:workstreams,id',
            'updates' => 'required|array',
            'updates.status' => 'sometimes|in:draft,active,on_hold,completed,cancelled',
            'updates.updated_by' => 'sometimes|exists:users,id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'workstream_ids.required' => 'Workstream IDs are required.',
            'workstream_ids.array' => 'Workstream IDs must be an array.',
            'workstream_ids.*.exists' => 'One or more selected workstreams do not exist.',
            'updates.required' => 'Updates are required.',
            'updates.array' => 'Updates must be an array.',
            'updates.status.in' => 'The selected status is invalid.',
            'updates.updated_by.exists' => 'The selected user does not exist.',
        ];
    }
}