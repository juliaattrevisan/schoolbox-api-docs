<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;

/**
 * Completion is unique in that it is a one-way transform.
 *
 * This is because both Teacher- and Self-assessed are considered '
 *
 * The NORMALISED_* constants are floats to maintain interface, but their
 * actual value has no semantic meaning.
 * Hence, we cannot map back from
 */
class Completion extends AbstractMarkScheme
{
    // Note: These are enum, NOT representative of real normalised marks.
    // Set to negative numbers to ensure distinct from valid real normalised marks
    // Pass these to getHumanReadableMark() to transform into a human-readable form
    const NORMALISED_INCOMPLETE                = -0;
    const NORMALISED_SELF_ASSESSED             = -1;
    const NORMALISED_TEACHER_ASSESSED          = -2;
    const NORMALISED_SELF_AND_TEACHER_ASSESSED = -3;

    const HUMAN_READABLE_INCOMPLETE                = 'Not Completed';
    const HUMAN_READABLE_SELF_ASSESSED             = 'Completed';
    const HUMAN_READABLE_TEACHER_ASSESSED          = 'Completed';
    const HUMAN_READABLE_SELF_AND_TEACHER_ASSESSED = 'Completed';

    private static $validHumanReadableMarks = [
        self::HUMAN_READABLE_INCOMPLETE,
        self::HUMAN_READABLE_SELF_ASSESSED,
        self::HUMAN_READABLE_TEACHER_ASSESSED,
        self::HUMAN_READABLE_SELF_AND_TEACHER_ASSESSED,
    ];


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
        // Note that this does not range-check normalised-mark, as it is enum, not real value

        switch($normalisedMark){
            case self::NORMALISED_INCOMPLETE:
                return self::HUMAN_READABLE_INCOMPLETE;
            case self::NORMALISED_SELF_ASSESSED:
                return self::HUMAN_READABLE_SELF_ASSESSED;
            case self::NORMALISED_TEACHER_ASSESSED:
                return self::HUMAN_READABLE_TEACHER_ASSESSED;
            case self::NORMALISED_SELF_AND_TEACHER_ASSESSED:
                return self::HUMAN_READABLE_SELF_AND_TEACHER_ASSESSED;
            default:
                throw new InvalidArgumentException("Unknown normalised mark: $normalisedMark");
        }
    }

    /**
     * For now, no normalised mark is returned for 'Completion' markScheme.
     * In the future, this may return {0|100} to represent {Incomplete|Complete}
     *
     * @param string $humanReadableMark eg 'Self-assessed'
     * @return null
     */
    public function getNormalisedMark($humanReadableMark)
    {
        if(!in_array($humanReadableMark, self::$validHumanReadableMarks)){
            throw new InvalidArgumentException("Unknown human readable mark: $humanReadableMark");
        }
        return null;
    }
}