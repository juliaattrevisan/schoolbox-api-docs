<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Collection;

class ValidDescriptorRow extends \Alaress\Specification\Base
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
    public function isSatisfiedBy($descriptorRow) : bool
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
        // (Descriptors with no points OR description simply don't get posted, so we don't need to worry about them)
        $descriptorHasValue = function ($desc) {
            /** @var Descriptor $desc */
            return \strlen($desc->getValue()) > 0;
        };

        $descriptorValue = function ($desc) {
            /** @var Descriptor $desc */
            return $desc->getValue();
        };

        $descriptors = $descriptors->filter($descriptorHasValue)->map($descriptorValue);

        $prevVal   = $prevDiff = null;
        foreach($descriptors as $val) {
            // Not the first item?
            if(null !== $prevVal){
                $diff = $val - $prevVal;

                // No increase/decrease ?
                if(0 === $diff){
                    return false;
                }

                // Do we have a prev diff to compare against?
                // Accounts for both ascending and descending possibilities
                if(null !== $prevDiff && (($prevDiff > 0 && $diff < 0) || ($prevDiff < 0 && $diff > 0))) {
                    return false;
                }
                $prevDiff = $diff;
            }
            $prevVal = $val;
        }

        return true;
    }

}