<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;

/**
 * A MarkScheme is used for conversion between HumanReadable and Normalised Marks.
 * A Normalised Mark is a float between 0.0 and 100.0 inclusive.
 */
interface IMarkScheme
{
    // Range boundaries for normalised marks
    const NORMALISED_MARK_MIN = 0.0;
    const NORMALISED_MARK_MAX = 100.0;

    /**
     * Is this mark scheme quantitative?
     *
     * In other words, are the human-readable marks it produces able to be compared as numbers?
     * (Percentages and total marks are examples of quantitative data.)
     *
     * This should always return the opposite of `isQualitative()`.
     *
     * @return bool
     */
    public function isQuantitative();

    /**
     * Is this mark scheme qualitative?
     *
     * In other words, are the human-readable marks it produces unable to be compared as numbers?
     * (Letter grades and yes/no options are examples of qualitative data.)
     *
     * This should always return the opposite of `isQuantitative()`.
     *
     * @return bool
     */
    public function isQualitative();

    /**
     * Returns a string representation of a mark.
     *
     * If a Participant has no Response and/or Feedback, $normalisedMark and $humanReadable will be null
     *
     * @param float|null     $normalisedMark
     * @param IResponse|null $response
     * @return string|null
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null);

    /**
     * Returns a float from 0 to 100.0 inclusive.
     *
     * If a Participant has no Response and/or Feedback, $normalisedMark and $humanReadable will be null
     *
     * @param string|null $humanReadableMark
     * @param IResponse|null $response
     * @return float|null
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null);

    /**
     * Get the Mark Type's human-readable title
     *
     * @return string
     */
    public static function getTitle();


    /**
     * Return the published human-readable mark for the specified participant
     *
     * @param Participant $participant
     * @return string|null
     */
    public function getPublishedHumanReadableMark(Participant $participant);
}
