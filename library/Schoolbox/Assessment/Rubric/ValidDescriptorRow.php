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

    public function __construct($usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }


    public function isSatisifedBy($descriptorRow) : bool
    {
        return $this->isMonotonic($descriptorRow) && $this->validDescriptorValues($descriptorRow);
    }


    private function isMonotonic($descriptorRow) : bool
    {
        // TODO
    }

    private function validDescriptorValues($descriptorRow) : bool
    {
        foreach($descriptorRow as $descriptor) {
            if (! (new ValidDescriptor($this->usesMarkRange))->isSatisifedBy($descriptor) ) {
                return false;
            }
        }

        return true;
    }

}