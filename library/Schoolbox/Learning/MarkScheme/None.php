<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;

class None extends AbstractMarkScheme
{
    /**
     * No human-readable mark returned
     *
     * @param float $normalisedMark
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);
        return null;
    }

    /**
     * No normalised mark returned
     *
     * @param string $humanReadableMark
     * @return null
     */
    public function getNormalisedMark($humanReadableMark)
    {
        return null;
    }
}