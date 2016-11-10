<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use \InvalidArgumentException;
use Schoolbox\Learning\Assessment\IResponse;

/**
 * Common logic for 'TotalMark' schemes
 */
abstract class TotalMarkAbstract extends AbstractMarkScheme
{
    const TITLE = 'Total Mark';

    /**
     * @var int|float
     */
    private $totalMark;

    /**
     * This type is comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = true;

    /**
     * @param int|float $totalMark
     */
    public function __construct($totalMark)
    {
        $this->validateTotalMark($totalMark);

        // Intentional bypass of setter(). Subclasses throw BadMethodException.
        $this->totalMark = $totalMark;
    }

    /**
     * Returns a string representation of a normalised Mark
     *
     * Granularity of numerator & denominator depends on sub-class
     *
     * @param float|null     $normalisedMark
     * @param IResponse|null $response
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string|null
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if(is_null($normalisedMark)){
            return null;
        }

        $this->validateNormalisedMark($normalisedMark);

        $rounded = (round($normalisedMark * $this->getTotalMark() / IMarkScheme::NORMALISED_MARK_MAX
                / $this->getGranularityNumerator())) * $this->getGranularityNumerator();

        $total = (round($this->getTotalMark() / $this->getGranularityDenominator()))
                * $this->getGranularityDenominator();

        return "$rounded / $total";
    }

    /**
     * Converts string numerator to float using stored denominator
     *
     * @param string         $humanReadableMark
     * @param IResponse|null $response
     * @return float|null Returns null if denominator = 0
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        $matches = [];
        $pattern = '/^\s*([0-9.]+)/';
        if(!preg_match($pattern, $humanReadableMark, $matches)){
            throw new InvalidArgumentException("Unable to parse numerator: $humanReadableMark");
        }

        // Prevent divide by 0 Errors
        if(0 == $this->getTotalMark()){
            return null;
        }

        $floatVal = floatval($matches[1] / $this->getTotalMark() * self::NORMALISED_MARK_MAX);
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }

    /**
     * @param float $totalMark
     */
    abstract protected function validateTotalMark($totalMark);

    /* Getters / Setters
     */

    /**
     * @return int|float
     */
    public function getTotalMark()
    {
        return $this->totalMark;
    }

    /**
     * Type depends on subclass
     *
     * @param int|float $totalMark
     * @return static
     */
    protected function setTotalMark($totalMark)
    {
        $this->totalMark = $totalMark;
        return $this;
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