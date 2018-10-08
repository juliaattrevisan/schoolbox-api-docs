<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Grid;


class ValidDescriptor extends \Alaress\Specification\Base
{
    private $usesMarkRange;

    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }

    /**
     * @param Descriptor $descriptor
     * @return bool
     */
    public function isSatisfiedBy($descriptor) : bool
    {
        // Null descriptors are ok.
        if (null === $descriptor) {
            return true;
        }
        $isADescriptor = $descriptor instanceof Descriptor;
        $usesMarkRange = $this->usesMarkRange;
        $hasDecimals = $this->hasDecimals($descriptor);
        return $isADescriptor && !($usesMarkRange && $hasDecimals);
    }

    /**
     * @param Descriptor $descriptor
     * @return bool
     */
    private function hasDecimals(Descriptor $descriptor) : bool
    {
        return preg_match('/\.\d+$/', $descriptor->getMaxValue()) === 1;
    }
}