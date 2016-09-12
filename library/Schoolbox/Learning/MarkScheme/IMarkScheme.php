<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

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
     * @param float $normalisedMark
     * @return string|null
     */
    public function getHumanReadableMark($normalisedMark);

    /**
     * Returns a float from 0 to 100.0 inclusive.
     *
     * @param string $humanReadableMark
     * @return float|null
     */
    public function getNormalisedMark($humanReadableMark);


}