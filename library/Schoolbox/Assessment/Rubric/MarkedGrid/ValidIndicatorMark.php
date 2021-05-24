<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\Descriptor;

/**
 * Spec pattern for validating an indicator mark.
 *
 * Rules:
 * * If no descriptor is selected, or the descriptor has no value: the mark
 *   MUST be null
 * * Otherwise, the mark MUST be within the descriptor's mark range, and
 *   MUST NOT be null
 */
class ValidIndicatorMark extends \Alaress\Specification\Base
{
    /**
     * @var null|Descriptor
     */
    private $descriptor;


    /**
     * Constructor
     *
     * @param null|Descriptor $descriptor The descriptor that was selected to
     *                                    create this indicator mark.
     */
    public function __construct(?Descriptor $descriptor)
    {
        $this->descriptor = $descriptor;
    }


    /**
     * Validate this candidate value.
     *
     * @param null|float $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if ($this->descriptor instanceof Descriptor && $this->descriptor->hasValue()) {
            $minValue  = $this->descriptor->getMinValue();
            $maxValue  = $this->descriptor->getMaxValue();
            $value     = filter_var($candidate, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            if (null === $value) {
                return false;
            }

            $isInRange = $minValue <= $candidate && $candidate <= $maxValue;

            // Is this a ranged value? If so for now it must be a whole number
            if ($this->descriptor->hasRange()) {
                $intValue = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                if (null === $intValue) {
                    return false;
                }
            }

            return $isInRange;
        }
        return '' === (string)$candidate;
    }
}
