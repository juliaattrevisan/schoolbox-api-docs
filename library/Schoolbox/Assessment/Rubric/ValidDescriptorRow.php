<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric;


class ValidDescriptorRow
{
    private $usesMarkRange;

    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }


    public function isSatisifedBy($descriptorRow) : bool
    {
        if ($this->usesMarkRange) {
            return $this->isMonotonic($descriptorRow);
        }
        return true;
    }


    private function isMonotonic($descriptorRow) : bool
    {
        // TODO
        return false;
    }

}