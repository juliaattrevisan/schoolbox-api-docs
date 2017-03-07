<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

/**
 * Letter grade mark scheme, with no upper or lower bound on its normalised mark.
 *
 * This is not used by any assessment types, but is used in the markbook -
 * extremely high or low quiz marks could cause a student's overall mark to be
 * above or below the normalised min or max, and thus cause an exception when
 * rendering a human-readable mark.
 */
class LetterGradeWithoutBounds extends LetterGrade
{
    /**
     * Any value is okay, as long as it's numeric type
     * (that is checked in $this->validateNormalisedMarkType).
     *
     * @param float $normalisedMark
     */
    protected function validateNormalisedMarkValue($normalisedMark)
    {
    }
}
