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
        // Percentage mark types (Letter Grade and Percentage) are always sent to the backend as values between 0-100
        $max = $this->markType === 'Total Mark' ? $this->capabilityMax : 100;

        return is_numeric($capabilityMark) && $capabilityMark >= 0 && $capabilityMark <= $max;
    }
}