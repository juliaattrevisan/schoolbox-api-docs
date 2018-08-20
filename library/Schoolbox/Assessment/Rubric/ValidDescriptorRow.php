<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric;

use Schoolbox\Collection;

class ValidDescriptorRow
{
    private $usesMarkRange;

    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }


    /**
     * @param Collection|Descriptor[] $descriptorRow
     * @return bool
     */
    public function isSatisifedBy($descriptorRow) : bool
    {
        // We only enforce monotonicity if mark range is in use
        if ($this->usesMarkRange) {
            return $this->isMonotonic($descriptorRow);
        }
        return true;
    }

    /**
     * @param Collection|Descriptor[] $descriptors
     * @return bool
     */
    private function isMonotonic($descriptors) : bool
    {
        // First remove any descriptors from the list that don't have values
        // since value-less descriptors are permitted in amongst valued descriptors
        $descriptorValue = function ($desc) {
            /** @var Descriptor $desc */
            return $desc->getValue();
        };

        $descriptors = $descriptors->filter($descriptorValue)->map($descriptorValue);

        // A single descriptor (or less) is a special case - always valid
        if (\count($descriptors) < 2) {
            return true;
        }
        // TODO compare descriptor values

        return true;
    }

}