<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use \InvalidArgumentException;

/**
 * Common logic for 'TotalMark' schemes
 */
abstract class TotalMarkAbstract extends AbstractMarkScheme
{
    /**
     * @var int|float
     */
    private $totalMark;

    /**
     * @param int|float $totalMark
     */
    public function __construct($totalMark)
    {
        $this->validateTotalMark($totalMark);
        $this->setTotalMark($totalMark);
    }

    /**
     * Returns a string representation of a normalised Mark
     *
     * Granularity of numerator & denominator depends on sub-class
     *
     * @param float $normalisedMark
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);

        $rounded = (round($normalisedMark / $this->getGranularityNumerator())) * $this->getGranularityNumerator();
        $total = (round($this->getTotalMark() / $this->getGranularityDenominator())) * $this->getGranularityDenominator();

        return "$rounded / $total";
    }

    /**
     * Converts string numerator to float using stored denominator
     *
     * @param string $humanReadableMark
     * @return float
     */
    public function getNormalisedMark($humanReadableMark)
    {
        $matches = [];
        $pattern = '^\s*([0-9.]+)';
        if(!preg_match($pattern, $humanReadableMark, $matches)){
            throw new InvalidArgumentException("Unable to parse numerator: $humanReadableMark");
        }

        $floatVal = floatval($matches[1] / $this->getTotalMark());
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }

    /**
     * @param int|float $totalMark
     */
    abstract protected function validateTotalMark($totalMark);

    /* Getters / Setters
     */

    /**
     * @return int|float
     */
    protected function getTotalMark()
    {
        return $this->totalMark;
    }

    /**
     * Type depends on subclass
     *
     * @param int|float $totalMark
     * @return static
     */
    public function setTotalMark($totalMark)
    {
        $this->totalMark = $totalMark;
    }

    /**
     * @return float
     */
    abstract protected function getGranularityNumerator();

    /**
     * @return float
     */
    abstract protected function getGranularityDenominator();

    /**
     * @return int|float
     */
    abstract protected function getTotalMarkMin();
}