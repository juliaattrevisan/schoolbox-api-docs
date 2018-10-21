<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
            $minValue = $this->descriptor->getMinValue();
            $maxValue = $this->descriptor->getMaxValue();
            return ($minValue <= $candidate && $candidate <= $maxValue);
        }
        return null === $candidate;
    }
}
