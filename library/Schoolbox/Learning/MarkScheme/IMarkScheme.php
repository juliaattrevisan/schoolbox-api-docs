<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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


}