<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

/**
 * Percentage mark scheme, with no upper or lower bound.
 *
 * This is not used by any assessment types, but is used in the markbook -
 * quizzes may have a mark outside the conventional upper or lower bound, and
 * the percentage mark scheme will error out if it tries to render a mark
 * outside the bound.
 */
class PercentageWithoutBounds extends Percentage
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
