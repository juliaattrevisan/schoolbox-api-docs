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


    public function isSatisifedBy(DescriptorList $descriptorRow) : bool
    {
        return $this->isMonotonic($descriptorRow) && $this->validDescriptorValues($descriptorRow);
    }


    private function isMonotonic(DescriptorList $descriptorRow) : bool
    {
        // TODO
    }

    private function validDescriptorValues(DescriptorList $descriptorRow) : bool
    {
        foreach($descriptorRow as $descriptor) {
            if (! (new ValidDescriptor($this->usesMarkRange))->isSatisifedBy($descriptor) ) {
                return false;
            }
        }

        return true;
    }

}