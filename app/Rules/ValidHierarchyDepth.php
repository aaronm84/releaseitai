<?php

namespace App\Rules;

use App\Models\Workstream;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a workstream hierarchy depth does not exceed the maximum allowed depth.
 *
 * This validation rule ensures that when setting a parent workstream, the resulting
 * hierarchy will not exceed the maximum depth limit defined in the Workstream model.
 * This prevents infinitely deep hierarchies and maintains performance.
 *
 * @package App\Rules
 */
class ValidHierarchyDepth implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Checks if the proposed parent workstream would cause the hierarchy to exceed
     * the maximum allowed depth. Fails validation if the parent is already at
     * or above the maximum depth.
     *
     * @param string $attribute The name of the attribute being validated
     * @param mixed $value The parent workstream ID being validated
     * @param Closure $fail Callback to call if validation fails
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value) {
            $parent = Workstream::find($value);
            if ($parent && $parent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                $fail('Workstream hierarchy cannot exceed 3 levels deep.');
            }
        }
    }
}