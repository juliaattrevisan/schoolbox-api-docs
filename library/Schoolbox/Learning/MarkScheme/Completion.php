<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;

class Completion extends AbstractMarkScheme
{
    const NORMALISED_INCOMPLETE = self::NORMALISED_MARK_MIN;
    const NORMALISED_COMPLETE   = self::NORMALISED_MARK_MAX;

    const HUMAN_READABLE_INCOMPLETE = 'Not Completed';
    const HUMAN_READABLE_COMPLETE   = 'Completed';


    /**
     * Returns a string representation of a normalised Mark
     *
     * @param float $normalisedMark
     * @throws RangeException if normalised mark is outside valid range
     * @throws InvalidArgumentException if enum not matched
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);

        switch($normalisedMark){
            case self::NORMALISED_INCOMPLETE:
                return self::HUMAN_READABLE_INCOMPLETE;
            case self::NORMALISED_COMPLETE:
                return self::HUMAN_READABLE_COMPLETE;
            default:
                throw new InvalidArgumentException("Unknown normalised mark: $normalisedMark");
        }
    }

    /**
     * Returns 0|100 representing Incomplete|Complete
     *
     * @param string $humanReadableMark eg 'Completed'
     * @return float
     */
    public function getNormalisedMark($humanReadableMark)
    {
        switch($humanReadableMark){
            case self::HUMAN_READABLE_INCOMPLETE:
                return self::NORMALISED_INCOMPLETE;
            case self::HUMAN_READABLE_COMPLETE:
                return self::NORMALISED_COMPLETE;
            default:
                throw new InvalidArgumentException("Unknown human-readable mark: $humanReadableMark");
        }
    }
}