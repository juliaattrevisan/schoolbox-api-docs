<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

class WeightedAggregator
{
    /**
     * Number of decimal points to use in the final calculation
     * We've used 2 in most places, so let's start with that as a default value
     */
    private const int PRECISION = 2;


    /**
     * Calculate a weighted aggregation contribution of a rubric capability or indicator.
     *
     * Jez and I have implemented a data struct where numerators and denominators are stored per student per cap/ind,
     * as a result of an accumulation over the entire data set; the weighted average becomes a simple division of those
     * two data points.
     *
     * @param float|null $numerator
     * @param float|null $denominator
     * @return array|null
     */
    public function calculate(
        ?float $numerator,
        ?float $denominator,
    ): ?array {
        /* Avoid divide-by-zero scenarios: exit early
         * Note that zero is a valid $numerator value; null means "nothing here to calculate"
         */
        if (null === $numerator) {
            return null;
        }

        /* Falsey/zero/null denominators: exit early
         */
        if (!$denominator) {
            return null;
        }

        $val = $numerator / $denominator;
        $val = round($val, self::PRECISION);

        /* Diagnostic
         */
        $diagnostic = sprintf(
            "%s/%s = %s",
            $numerator,
            $denominator,
            $val
        );

        return [
            'diagnostic' => $diagnostic,
            'val' => $val,
        ];
    }
}
