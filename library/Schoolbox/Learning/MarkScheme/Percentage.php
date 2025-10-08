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

class Percentage extends AbstractMarkScheme
{
    public const SLUG = 'percent';

    public const TITLE = 'Percentage';


    /**
     * This type is comparable numerically.
     *
     * @var bool
     */
    public const QUANTITATIVE = true;


    /**
     * Template filename
     *
     * @var string
     */
    protected string $template = 'Percentage';


    /**
     * @inheritdoc
     */
    public function getHumanReadableMark($normalisedMark, ?IResponse $response = null): ?string
    {
        if (null === $normalisedMark) {
            return null;
        }

        $normalisedMark = $this->filterNormalisedMark($normalisedMark);

        $percentage = round($normalisedMark, $this::PRECISION);
        return "$percentage %";
    }

    /**
     * Converts percentage string to float, and ensures float lies between 0.0 and 100.0 inclusive
 * @param string             $humanReadableMark eg '90 %'
     * @param IResponse|null $response
     * @return float|null
     *@throws RangeException if parsed value is outside valid range
     * @throws InvalidArgumentException If cannot parse a float
     */
    public function getNormalisedMark($humanReadableMark, ?IResponse $response = null): ?float
    {
        // Cannot merely use floatval(), as that returns 0 on failed parsing.

        $matches = [];
        $pattern = '/^\s*(-?[0-9.]+)/';
        if (!preg_match($pattern, $humanReadableMark, $matches)) {
            throw new InvalidArgumentException("Unable to parse percentage value: $humanReadableMark");
        }

        $floatVal = (float)$matches[1];
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }

    /**
     * For percent marks, the form input is the percent value only:
     * append a % symbol to the end of it.
     *
     * @param string|null $formInput
     * @return string|null
     * @throws InvalidArgumentException if there's no numeric component to
     *                                  `$formInput`
     */
    public function convertFormInputToHumanReadableMark(?string $formInput): ?string
    {
        if (!isset($formInput)) {
            return null;
        }
        $floatVal = round($this->extractNumericComponent($formInput), self::PRECISION);
        return sprintf("%s %%", $floatVal);
    }

    /**
     * Strip the % symbol from the end of the human-readable mark to get its
     * form input value.
     *
     * @param string|null $humanReadableMark
     * @return string|null
     */
    public function convertHumanReadableMarkToFormInput(?string $humanReadableMark): ?string
    {
        if (!isset($humanReadableMark)) {
            return null;
        }
        return (string)round($this->extractNumericComponent($humanReadableMark), self::PRECISION);
    }

    /**
     * @inheritDoc
     */
    public function getValueForJS($mark, ?float $maxValue, ?float $rowMaxSum)
    {
        $max      = $rowMaxSum ?: $maxValue;
        $normMark = $max ? ($mark / $max) * 100 : 0;
        return $this->getHumanReadableMark($normMark);
    }

    /**
     * Extract a numeric component out of `$input`.
     *
     * `$input` must start with a numeric component in order for it to be
     * extraced.
     *
     * @param string $input
     * @return float
     * @throws InvalidArgumentException if there's no numeric component to
     *                                  `$input`
     */
    private function extractNumericComponent(string $input): float
    {
        $matches = [];
        $pattern = '/^\s*(-?[0-9.]+)/';
        if (!preg_match($pattern, $input, $matches)) {
            throw new InvalidArgumentException("Unable to parse percentage value: $input");
        }
        return floatval($matches[1]);
    }
}
