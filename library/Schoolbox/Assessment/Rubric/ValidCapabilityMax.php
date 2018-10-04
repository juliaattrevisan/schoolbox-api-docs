<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric;


class ValidCapabilityMax extends \Alaress\Specification\Base
{
    private $markPerCap;

    public function __construct(bool $markPerCap)
    {
        $this->markPerCap = $markPerCap;
    }

    public function isSatisfiedBy($capabilityMax) : bool
    {
        if ($this->markPerCap) {
            return is_numeric($capabilityMax) && $capabilityMax > 0;
        }
        return true;
    }
}
