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
     * TODO Update all this lot
     * This individual formula is used to calculate how an indicator's mark (the selected descriptor's value) from a
     * particular grid contributes to its overall value across multiple grids in multiple assessments. It can also be
     * used to calculate a capability value (if applicable).
     *
     * Weighted contribution = (assessment weight/assessment total weight that used this capability or indicator where student had a mark)
     *                       * (rubric grid weight/total rubric grid weights in the assessment that used this capability or indicator)
     *                       * capability value or indicator value as a percentage between 0 and 100
     *
     * Overall value of a capability or indicator = sum of its weighted contributions
     *
     * (I'm not a statistician so maybe this needs to be double-checked more closely; thanks Jonathan Kerslake for
     * joining me in this adventure)
     *
     * @param float|null $numerator
     * @param float|null $denominator
     * @param string|null $prefix An optional prefix for diagnostic usage only
     * @return array|null
     */
    public function calculate(
        ?float $numerator,
        ?float $denominator,
        ?string $prefix,
    ): ?array {
        /* Avoid divide-by-zero scenarios: exit early
         */
        if (!$numerator) {
            return null;
        }

        if (!$denominator) {
            return null;
        }

        $val = $numerator / $denominator;
        $val = round($val, self::PRECISION);

        /* Diagnostic
         */
        $diagnostic = sprintf(
            "%s: %s/%s = %s",
            $prefix,
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
