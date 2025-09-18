<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveWorkstreamRequest extends FormRequest
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
            'new_parent_workstream_id' => 'nullable|exists:workstreams,id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'new_parent_workstream_id.exists' => 'The selected parent workstream does not exist.',
        ];
    }
}