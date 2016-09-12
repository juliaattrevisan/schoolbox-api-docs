<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;

class Percentage extends AbstractMarkScheme
{
    /**
     * Returns a string representation of a normalised Mark
     *
     * @param float $normalisedMark
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);

        $percentage = round($normalisedMark);
        return "$percentage %";
    }

    /**
     * Converts percentage string to float, and ensures float lies between 0.0 and 100.0 inclusive

     * @param string $humanReadableMark eg '90 %'
     * @throws InvalidArgumentException If cannot parse a float
     * @throws RangeException if parsed value is outside valid range
     * @return float
     */
    public function getNormalisedMark($humanReadableMark)
    {
        // Cannot merely use floatval(), as that returns 0 on failed parsing.

        $matches = [];
        $pattern = '/^\s*(-?[0-9.]+)/';
        if(!preg_match($pattern, $humanReadableMark, $matches)){
            throw new InvalidArgumentException("Unable to parse percentage value: $humanReadableMark");
        }

        $floatVal = floatval($matches[1]);
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }
}