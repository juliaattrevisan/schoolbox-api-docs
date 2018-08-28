<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric;


class ValidCapabilityMax
{
    public function isSatisifedBy($capabilityMax, $markPerCap, $rowMaxSum) {
        if ($markPerCap) {
            return is_numeric($capabilityMax) && $capabilityMax >= 0 && $capabilityMax <= $rowMaxSum;
        }
        return true;
    }
}