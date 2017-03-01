<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use RangeException;
use Schoolbox\Learning\Assessment\IResponse;

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
     * @param float     $normalisedMark
     * @param IResponse $response
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        try {
            return parent::getHumanReadableMark($normalisedMark, $response);

        } catch (RangeException $e) {
            // $normalisedMark is outside valid range - coerce it to within those ranges
            $normalisedMark = max($normalisedMark, IMarkScheme::NORMALISED_MARK_MIN);
            $normalisedMark = min($normalisedMark, IMarkScheme::NORMALISED_MARK_MAX);
            return parent::getHumanReadableMark($normalisedMark, $response);
        }
    }
}
