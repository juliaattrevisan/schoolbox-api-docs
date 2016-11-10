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
    const TITLE = 'Abstract Mark Scheme';

    /**
     * Does this mark scheme produce human-readable results that are comparable
     * as numbers?
     *
     * @var bool
     */
    protected $quantitative;

    /**
     * Bounds checks a normalised mark
     *
     * @param float $normalisedMark
     * @throws RangeException
     */
    protected function validateNormalisedMark($normalisedMark)
    {
        if(!(is_double($normalisedMark) || is_int($normalisedMark))){
            $type = gettype($normalisedMark);
            $msg = "Normalised mark type must be float or int: $normalisedMark ($type)";
            throw new \InvalidArgumentException($msg);
        }

        if ($normalisedMark < IMarkScheme::NORMALISED_MARK_MIN || $normalisedMark > IMarkScheme::NORMALISED_MARK_MAX) {
            $msg = 'Normalised mark must lie between '.IMarkScheme::NORMALISED_MARK_MIN.
                ' and '.IMarkScheme::NORMALISED_MARK_MAX.' inclusive: '.$normalisedMark;
            throw new RangeException($msg);
        }
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return static::TITLE;
    }

    /**
     * @return bool
     */
    public function isQuantitative()
    {
        return $this->quantitative;
    }

    /**
     * @return bool
     */
    public function isQualitative()
    {
        return !$this->quantitative;
    }
}