<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Quiz\Attempt as QuizAttempt;

/**
 * Common logic for 'TotalMark' schemes
 */
abstract class TotalMarkAbstract extends AbstractMarkScheme
{
    const TITLE = 'Total Mark';

    /**
     * Quiz can set this to NULL, but then must supply an IResponse to getHumanReadable()
     *
     * @var null|int|float
     */
    private $totalMark;

    /**
     * This type is comparable numerically.
     *
     * @var bool
     */
    const QUANTITATIVE = true;

    /**
     * @param null|int|float $totalMark
     * @throws RangeException If $totalMark is negative
     */
    public function __construct($totalMark)
    {
        $this->setTotalMark($totalMark);
    }


    /**
     * @param null|int|float $totalMark
     * @throws RangeException If $totalMark outside valid range
     */
    abstract protected function rangeCheck($totalMark);

    /**
     * Returns a string representation of a normalised Mark
     *
     * Granularity of numerator & denominator depends on sub-class
     *
     * @param float|null     $normalisedMark
     * @param IResponse|null $response
     * @return string|null
     * @throws RangeException If normalised mark is outside valid range
     * @throws \DomainException If unable to derive a denominator
     * @throws \InvalidArgumentException  If $normalisedMark isn't a numeric type
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if (null === $normalisedMark) {
            return null;
        }

        $normalisedMark = $this->filterNormalisedMark($normalisedMark);

        $denominator = $this->deriveDenominator($response);

        $rounded = round($normalisedMark * $denominator / IMarkScheme::NORMALISED_MARK_MAX
                / $this->getGranularityNumerator()) * $this->getGranularityNumerator();

        $total = round($denominator / $this->getGranularityDenominator()) * $this->getGranularityDenominator();

        return "$rounded / $total";
    }

    /**
     * Return the denominator to use for total mark calculations
     *
     * @param IResponse|null $response
     * @return float|int|null
     * @throws \DomainException If unable to derive a denominator
     */
    protected function deriveDenominator(IResponse $response = null)
    {
        if ($response instanceof QuizAttempt\Model) {
            return $response->getMarksAvailable();
        }
        if (null === $this->totalMark) {
            $message = 'Unable to derive a human readable mark without a denominator. Either total mark or QuizAttempt required';
            throw new \DomainException($message);
        }
        return $this->totalMark;
    }

    /**
     * Converts string numerator to float using stored denominator
     *
     * @param string         $humanReadableMark
     * @param IResponse|null $response
     * @return float|null Returns null if denominator = 0
     * @throws InvalidArgumentException If unable to parse numerator from human-readable mark
     * @throws RangeException If evaluated normalised mark is below 0 or above 100
     * @throws \DomainException If unable to derive a denominator
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        $matches = [];
        $pattern = '/^\s*(-?[0-9.]+)/';
        if (!preg_match($pattern, $humanReadableMark, $matches)) {
            throw new InvalidArgumentException("Unable to parse numerator: $humanReadableMark");
        }

        $denominator = $this->deriveDenominator($response);

        // Prevent divide by 0 Errors
        if (0 === (int)$denominator) {
            return null;
        }

        $floatVal = (float)($matches[1] / $denominator * self::NORMALISED_MARK_MAX);
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }

    /**
     * @inheritdoc
     */
    public function formatMark(?string $humanReadableMark): ?string
    {
        $denominator = round($this->deriveDenominator() / $this->getGranularityDenominator()) * $this->getGranularityDenominator();
        return "$humanReadableMark / $denominator";
    }

    /* Getters / Setters
     */

    /**
     * @param int|float|null $totalMark
     * @return $this
     * @throws RangeException
     */
    public function setTotalMark(?float $totalMark): self
    {
        $this->rangeCheck($totalMark);
        $this->totalMark = $totalMark;
        return $this;
    }


    /**
     * @return int|float
     */
    public function getTotalMark()
    {
        return $this->totalMark;
    }


    /**
     * @return float
     */
    abstract protected function getGranularityNumerator();

    /**
     * @return float
     */
    abstract protected function getGranularityDenominator();
}
