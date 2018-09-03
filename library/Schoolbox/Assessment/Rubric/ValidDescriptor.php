<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric;


class ValidDescriptor
{
    private $usesMarkRange;

    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }


    public function isSatisfiedBy(Descriptor $descriptor) : bool
    {
        return !($this->usesMarkRange && $this->hasDecimals($descriptor));
    }

    private function hasDecimals(Descriptor $descriptor) : bool
    {
       return preg_match('/\.\d+$/', $descriptor->getValue()) === 1 ? true : false;
    }
}