<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;


class ValidCapabilityMark extends \Alaress\Specification\Base
{
    private $capabilityMax;
    private $markType;

    public function __construct($capabilityMax, $markType)
    {
        $this->capabilityMax = $capabilityMax;
        $this->markType = $markType;
    }

    public function isSatisfiedBy($capabilityMark) : bool
    {
        // TODO aren't marks always normalised??? This statement is incorrect
        // TODO the marks coming through here are not normalised!
        // TODO is the calculation fix merged???!
        // Percentage mark types (Letter Grade and Percentage) are always sent to the backend as values between 0-100
        $max = $this->markType === 'Total Mark' ? $this->capabilityMax : 100;

        // TODO for debugging only, remove later
        $isNumeric = is_numeric($capabilityMark);
        $greaterOrEqualToZero = $capabilityMark >= 0;
        $lessThanOrEqualToMax = $capabilityMark <= $max;
        return $isNumeric && $greaterOrEqualToZero && $lessThanOrEqualToMax;
    }
}
