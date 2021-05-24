<?php

/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Collection;

/**
 * Spec pattern for a collection of descriptors.
 *
 * This pattern validates whether the set is monotonic: that is, if the
 * set of descriptors (when using the descriptor's value for comparison):
 * * is either ascending or descending in order
 * * contains no duplicate values
 *
 * Any descriptors without values are ignored, and this pattern only applies if
 * mark ranges are in use.
 */
class ValidMonotonicDescriptorRow extends \Alaress\Specification\Base
{
    /**
     * Does the assessment the descriptor set is part of use mark ranges?
     * This spec doesn't apply if it doesn't use mark ranges.
     *
     * @var bool
     */
    private $usesMarkRange;

    /**
     * Constructor
     *
     * @param bool $usesMarkRange
     */
    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }

    /**
     * @param Collection|Descriptor[] $descriptorRow
     * @return bool
     */
    public function isSatisfiedBy($descriptorRow): bool
    {
        // We only enforce monotonicity if mark range is in use
        if ($this->usesMarkRange) {
            return $this->isMonotonic($descriptorRow);
        }
        return true;
    }

    /**
     * @param Collection|Descriptor[] $descriptors
     * @return bool
     */
    private function isMonotonic($descriptors): bool
    {
        // First remove any descriptors from the list that don't have values
        // since value-less descriptors are permitted in amongst valued descriptors
        // (Descriptors with no points OR description simply don't get posted, so we don't need to worry about them)
        $descriptorHasValue = function ($desc) {
            /** @var Descriptor $desc */
            return $desc->getMaxValue() !== null;
        };

        $descriptorValue = function ($desc) {
            /** @var Descriptor $desc */
            return $desc->getMaxValue();
        };

        $descriptors = $descriptors->filter($descriptorHasValue)->map($descriptorValue);

        $prevVal = $prevDiff = null;
        foreach ($descriptors as $val) {
            // Not the first item?
            if (null !== $prevVal) {
                $diff = $val - $prevVal;

                // No increase/decrease ?
                if (0.0 === $diff) {
                    return false;
                }

                // Do we have a prev diff to compare against?
                // Accounts for both ascending and descending possibilities
                if (null !== $prevDiff && (($prevDiff > 0 && $diff < 0) || ($prevDiff < 0 && $diff > 0))) {
                    return false;
                }
                $prevDiff = $diff;
            }
            $prevVal = $val;
        }

        return true;
    }
}
