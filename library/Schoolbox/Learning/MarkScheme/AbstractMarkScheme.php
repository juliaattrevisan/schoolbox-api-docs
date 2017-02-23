<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use RangeException;
use Schoolbox\Learning\Assessment\Participant;

abstract class AbstractMarkScheme implements IMarkScheme
{
    const TITLE = 'Abstract Mark Scheme';

    /**
     * Does this mark scheme produce human-readable results that are comparable
     * as numbers?
     *
     * @var bool
     */
    protected $quantitative = false;

    /**
     * Bounds checks a normalised mark
     *
     * @param float $normalisedMark
     * @throws RangeException
     */
    protected function validateNormalisedMark($normalisedMark)
    {
        $this->validateNormalisedMarkType($normalisedMark);
        $this->validateNormalisedMarkValue($normalisedMark);
    }

    /**
     * Validates normalised mark type
     *
     * @param float $normalisedMark
     * @throws \InvalidArgumentException if $normalisedMark isn't a numeric type
     */
    protected function validateNormalisedMarkType($normalisedMark)
    {
        if(!(is_double($normalisedMark) || is_int($normalisedMark))){
            $type = gettype($normalisedMark);
            $msg = "Normalised mark type must be float or int: $normalisedMark ($type)";
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Validates normalised mark value
     *
     * @param float $normalisedMark
     * @throws \RangeException if $normalisedMark isn't between min/max
     */
    protected function validateNormalisedMarkValue($normalisedMark)
    {
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


    /**
     * @inheritdoc
     */
    public function getPublishedHumanReadableMark(Participant $participant)
    {
        $feedback = $participant->findMostRecentPublishedFeedback();
        return $feedback ? $feedback->getHumanReadableMark() : null;
    }
}
