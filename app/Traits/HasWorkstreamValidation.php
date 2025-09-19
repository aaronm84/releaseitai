<?php

namespace App\Traits;

use App\Rules\ValidHierarchyDepth;
use Illuminate\Validation\Rule;

trait HasWorkstreamValidation
{
    /**
     * Get common workstream validation rules.
     */
    protected function getWorkstreamValidationRules(): array
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
                new ValidHierarchyDepth(),
            ],
        ];
    }

    /**
     * Get workstream type validation rule.
     */
    protected function getWorkstreamTypeRule(): array
    {
        return ['required', Rule::in(['product_line', 'initiative', 'experiment'])];
    }

    /**
     * Get workstream status validation rule.
     */
    protected function getWorkstreamStatusRule(): array
    {
        return ['sometimes', Rule::in(['draft', 'active', 'on_hold', 'completed', 'cancelled'])];
    }

    /**
     * Get parent workstream validation rule.
     */
    protected function getParentWorkstreamRule(): array
    {
        return [
            'nullable',
            'exists:workstreams,id',
            new ValidHierarchyDepth(),
        ];
    }

    /**
     * Get workstream validation messages.
     */
    protected function getWorkstreamValidationMessages(): array
    {
        return [
            'type.in' => 'The type must be one of: product_line, initiative, experiment.',
            'parent_workstream_id.exists' => 'The selected parent workstream does not exist.',
            'owner_id.exists' => 'The selected owner does not exist.',
        ];
    }
}