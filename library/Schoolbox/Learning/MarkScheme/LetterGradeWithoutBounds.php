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
 * This is used by the project assessment type, and in the markbook - either
 * could contain quiz marks high or low enough to cause an overall mark to be
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
