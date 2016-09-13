<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use RangeException;

abstract class AbstractMarkScheme implements IMarkScheme
{
    /**
     * Bounds checks a normalised mark
     *
     * @param float $normalisedMark
     * @throws RangeException
     */
    protected function validateNormalisedMark($normalisedMark)
    {
        if(!(is_float($normalisedMark)|| is_double($normalisedMark) || is_int($normalisedMark))){
            throw new \InvalidArgumentException('Normalised mark type must be float, double, or int: '.gettype($normalisedMark));
        }

        if ($normalisedMark < IMarkScheme::NORMALISED_MARK_MIN || $normalisedMark > IMarkScheme::NORMALISED_MARK_MAX) {
            $msg = 'Normalised mark must lie between '.IMarkScheme::NORMALISED_MARK_MIN.
                ' and '.IMarkScheme::NORMALISED_MARK_MAX.' inclusive: '.$normalisedMark;
            throw new RangeException($msg);
        }
    }
}