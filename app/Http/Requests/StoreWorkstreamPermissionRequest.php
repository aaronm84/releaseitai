<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkstreamPermissionRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'permission_type' => 'required|in:view,edit,admin',
            'scope' => 'sometimes|in:workstream_only,workstream_and_children',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'A user ID is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'permission_type.required' => 'A permission type is required.',
            'permission_type.in' => 'The selected permission type is invalid.',
            'scope.in' => 'The selected permission scope is invalid.',
        ];
    }
}