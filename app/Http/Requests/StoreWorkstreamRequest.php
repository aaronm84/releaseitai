<?php

namespace App\Http\Requests;

use App\Traits\HasWorkstreamValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkstreamRequest extends FormRequest
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
        return $this->getWorkstreamValidationRules();
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return $this->getWorkstreamValidationMessages();
    }
}
