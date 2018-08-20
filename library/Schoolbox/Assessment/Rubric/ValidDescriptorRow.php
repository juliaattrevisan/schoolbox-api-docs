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
        $descriptorHasValue = function ($desc) {
            /** @var Descriptor $desc */
            return $desc->getValue();
        };
        $descriptors = $descriptors->filter($descriptorHasValue);

        if (\count($descriptors) < 2) {
            // A single descriptor value is a special case
            return true;
        }

        // Second descriptor minus first descriptor
        $initialDiff = $descriptors[1]->getValue() - $descriptors[0]->getValue();
        for ($i = 0; $i < \count($descriptors); $i++) {
            // skip first 2 elements cause we already compared them
            if ($i > 1) {
                $currentDiff = $descriptors[$i]->getValue() - $descriptors[$i-1]->getValue();
                if (! ($currentDiff >= $initialDiff) ) {
                    return false;
                }
            }
        }

        return true;
    }

}