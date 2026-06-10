<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use DebugBar\DebugBarException;

class WeightedAggregator
{
    /**
     * Calculate a weighted aggregation contribution of a rubric capability or indicator.
     *
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
     * @param float|null $assessmentWeight
     * @param float|null $assessmentTotalWeight
     * @param float|null $gridWeight
     * @param float|null $gridTotalWeight
     * @param float|null $normalisedMark A float between 0 and 100
     * @param string|null $prefix An optional prefix for diagnostic usage only
     * @return array|null
     */
    public function calculate(
        ?float $assessmentWeight,
        ?float $assessmentTotalWeight,
        ?float $gridWeight,
        ?float $gridTotalWeight,
        ?float $normalisedMark,
        ?string $prefix,
    ): ?array {
        /* Avoid divide-by-zero scenarios: exit early
         */
        if (!$assessmentTotalWeight) {
            return null;
        }

        if (!$gridTotalWeight) {
            return null;
        }

        /* normalised mark is null; this doesn't count toward anything and shouldn't be equated to zero.
         * Exit early instead.
         */
        if (null === $normalisedMark) {
            return null;
        }

        $val = ($assessmentWeight / $assessmentTotalWeight) * ($gridWeight / $gridTotalWeight) * $normalisedMark;
        $val = (float)number_format($val, 2);

        /* Diagnostic
         */
        $diagnostic = (sprintf(
             "%s: (%s/%s) * (%s/%s) * %s = %s",
             $prefix,
             $assessmentWeight,
             $assessmentTotalWeight,
             $gridWeight,
             $gridTotalWeight,
             $normalisedMark,
             $val
        ));

        return [
            'diagnostic' => $diagnostic,
            'val' => $val,
        ];
    }
}
