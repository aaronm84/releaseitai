<?php

namespace App\Http\Requests;

use App\Models\StakeholderRelease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStakeholderReleaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return false;
        }

        // For now, implement basic authorization:
        // User must be the owner of the workstream that contains this release
        $release = $this->route('release');
        if ($release instanceof \App\Models\Release) {
            return auth()->id() === $release->workstream->owner_id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::in(StakeholderRelease::ROLES)
            ],
            'notification_preference' => [
                'required',
                Rule::in(StakeholderRelease::NOTIFICATION_PREFERENCES)
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'role.in' => 'The selected role is invalid. Valid roles are: ' . implode(', ', StakeholderRelease::ROLES),
            'notification_preference.in' => 'The selected notification preference is invalid. Valid preferences are: ' . implode(', ', StakeholderRelease::NOTIFICATION_PREFERENCES),
        ];
    }
}
