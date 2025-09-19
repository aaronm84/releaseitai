<?php

namespace App\Rules;

use App\Models\Workstream;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a workstream move operation maintains valid hierarchy constraints.
 *
 * This validation rule ensures that when moving a workstream to a new parent,
 * the operation will not create circular references or exceed maximum hierarchy depth.
 * Used specifically for workstream update operations where hierarchical relationships
 * are being modified.
 *
 * @package App\Rules
 */
class ValidWorkstreamMove implements ValidationRule
{
    /**
     * Create a new validation rule instance.
     *
     * @param Workstream|null $workstream The workstream being moved (null for creation)
     */
    public function __construct(private ?Workstream $workstream = null)
    {
    }

    /**
     * Run the validation rule.
     *
     * Validates that the proposed parent workstream move is valid by checking:
     * 1. No circular hierarchy would be created
     * 2. Maximum hierarchy depth would not be exceeded
     *
     * @param string $attribute The name of the attribute being validated
     * @param mixed $value The parent workstream ID being validated
     * @param Closure $fail Callback to call if validation fails
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value && $this->workstream) {
            // Check for circular hierarchy
            if ($this->workstream->wouldCreateCircularHierarchy($value)) {
                $fail('Cannot create circular workstream relationship.');
            }

            // Check depth limit
            $parent = Workstream::find($value);
            if ($parent && $parent->getHierarchyDepth() >= Workstream::MAX_HIERARCHY_DEPTH) {
                $fail('Workstream hierarchy cannot exceed 3 levels deep.');
            }
        }
    }
}