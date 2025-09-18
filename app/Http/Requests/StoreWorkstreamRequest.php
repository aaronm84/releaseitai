<?php

namespace App\Http\Requests;

use App\Models\Workstream;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkstreamRequest extends FormRequest
{
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
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['product_line', 'initiative', 'experiment'])],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'on_hold', 'completed', 'cancelled'])],
            'owner_id' => 'required|exists:users,id',
            'parent_workstream_id' => [
                'nullable',
                'exists:workstreams,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parent = Workstream::find($value);
                        if ($parent && $parent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                            $fail('Workstream hierarchy cannot exceed 3 levels deep.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type must be one of: product_line, initiative, experiment.',
            'parent_workstream_id.exists' => 'The selected parent workstream does not exist.',
        ];
    }
}
