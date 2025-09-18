<?php

namespace App\Http\Requests;

use App\Models\StakeholderRelease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStakeholderReleaseRequest extends FormRequest
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
        $releaseId = $this->route('release') instanceof \App\Models\Release
            ? $this->route('release')->id
            : $this->route('release');

        return [
            'stakeholders' => ['required', 'array', 'min:1'],
            'stakeholders.*.user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('stakeholder_releases', 'user_id')
                    ->where('release_id', $releaseId)
            ],
            'stakeholders.*.role' => [
                'required',
                Rule::in(StakeholderRelease::ROLES)
            ],
            'stakeholders.*.notification_preference' => [
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
            'stakeholders.*.user_id.unique' => 'This user is already a stakeholder for this release.',
            'stakeholders.*.role.in' => 'The selected role is invalid. Valid roles are: ' . implode(', ', StakeholderRelease::ROLES),
            'stakeholders.*.notification_preference.in' => 'The selected notification preference is invalid. Valid preferences are: ' . implode(', ', StakeholderRelease::NOTIFICATION_PREFERENCES),
        ];
    }
}
