<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;

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
     * @param null|float     $normalisedMark
     * @param IResponse|null $response          The response we wish to get the mark from
     * @return string|null
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null);

    /**
     * Returns a float from 0 to 100.0 inclusive.
     *
     * If a Participant has no Response and/or Feedback, $normalisedMark and $humanReadable will be null
     *
     * @param string|null $humanReadableMark
     * @param IResponse|null $response          Most recent submitted response
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
     * Generates a context for this mark scheme.
     *
     * TODO I don't really want to do this, this should instead be a decorator?
     *
     * @return array
     */
    public function generateContext(): array;

    /**
     * Converts the mark to Javascript Comparable format
     * @param            $mark
     * @param float|null $maxValue
     * @param float|null $rowMaxSum
     * @return mixed
     */
    public function getValueForJS($mark, ?float $maxValue, ?float $rowMaxSum);
}
