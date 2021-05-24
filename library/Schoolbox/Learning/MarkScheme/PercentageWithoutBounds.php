<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

/**
 * Percentage mark scheme, with no upper or lower bound on its normalised mark.
 *
 * @deprecated 17.0; unused now human-readable marks can't raise range exceptions
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
