<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;


class ValidCapabilityMark
{
    public function isValid($capabilityMark, $capabilityMax, $markType) {
        // Percentage mark types (Letter Grade and Percentage) are always sent to the backend as values between 0-100
        $max = $markType === "Total Mark" ? $capabilityMax : 100;

        return is_numeric($capabilityMark) && $capabilityMark >= 0 && $capabilityMark <= $max;
    }
}