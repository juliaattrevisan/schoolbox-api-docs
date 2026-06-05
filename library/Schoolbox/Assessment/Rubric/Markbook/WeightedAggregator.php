<?php

namespace Schoolbox\Assessment\Rubric\Markbook;

class WeightedAggregator
{

    public function calculate(
        ?float $assessmentWeight,
        ?float $assessmentTotalWeight,
        ?float $gridWeight,
        ?float $gridTotalWeight,
        ?float $normalisedMark
    ): ?float {
        return ($assessmentWeight/$assessmentTotalWeight) * ($gridWeight/$gridTotalWeight) * ($normalisedMark/100);
    }
}