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
    public const PRECISION = 2;

    const TITLE = 'Percentage';

    /**
     * This type is comparable numerically.
     *
     * @var bool
     */
    const QUANTITATIVE = true;

    /**
     * @inheritdoc
     * @throws RangeException If $normalisedMark is outside 0.0 to 100.0
     * @throws \InvalidArgumentException  If $normalisedMark isn't a numeric type
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
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

     * @param string         $humanReadableMark eg '90 %'
     * @param IResponse|null $response
     * @throws InvalidArgumentException If cannot parse a float
     * @throws RangeException if parsed value is outside valid range
     * @return float
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        // Cannot merely use floatval(), as that returns 0 on failed parsing.

        $matches = [];
        $pattern = '/^\s*(-?[0-9.]+)/';
        if(!preg_match($pattern, $humanReadableMark, $matches)){
            throw new InvalidArgumentException("Unable to parse percentage value: $humanReadableMark");
        }

        $floatVal = floatval($matches[1]);
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }


    public function getValueForJS($mark, ?float $maxValue)
    {
        $normMark = $maxValue ? ($mark / $maxValue) * 100 : 0;
        return round($normMark, self::PRECISION);
    }
}
