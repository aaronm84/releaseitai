<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexWorkstreamRequest extends FormRequest
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
            'type' => 'nullable|in:product_line,initiative,experiment',
            'status' => 'nullable|in:draft,active,on_hold,completed,cancelled',
            'parent_workstream_id' => 'nullable|string', // can be 'null' string or numeric
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The selected type is invalid.',
            'status.in' => 'The selected status is invalid.',
        ];
    }
}