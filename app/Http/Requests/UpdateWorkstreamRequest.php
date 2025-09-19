<?php

namespace App\Http\Requests;

use App\Rules\ValidWorkstreamMove;
use App\Traits\HasWorkstreamValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkstreamRequest extends FormRequest
{
    use HasWorkstreamValidation;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workstream = $this->route('workstream');
        $rules = $this->getWorkstreamValidationRules();

        // Make all fields optional for updates
        $rules['name'] = 'sometimes|string|max:255';
        $rules['description'] = 'sometimes|nullable|string';
        $rules['type'] = ['sometimes', Rule::in(['product_line', 'initiative', 'experiment'])];
        $rules['owner_id'] = 'sometimes|exists:users,id';
        $rules['parent_workstream_id'] = [
            'sometimes',
            'nullable',
            'exists:workstreams,id',
            new ValidWorkstreamMove($workstream),
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return $this->getWorkstreamValidationMessages();
    }
}
