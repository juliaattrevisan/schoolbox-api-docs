<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

/**
 * Builds a suitable data structure for rendering the Diploma Programme
 * components.
 */
class DiplomaProgrammeGraph
{
    /**
     * Generates and returns a data structure, suitable for passing to twig.
     *
     * @return array
     */
    public function toContext(): array
    {
        // There are two types of assessment that could feed this graph:
        // MYP students: a rubric with total indicator mark of 32
        // Mapping from total rubric indicator marks to graph value:
        // 0:     0
        // 1-5:   1
        // 6-9:   2
        // 10-14: 3
        // 15-18: 4
        // 19-23: 5
        // 24-27: 6
        // 28-32: 7
        // DP students: a due work with a max mark of 7


        // Dummy data: just output counts
        return [
            'data' => [
                0 => 0,
                1 => 3,
                2 => 4,
                3 => 7,
                4 => 11,
                5 => 9,
                6 => 8,
                7 => 1,
            ],
            'studentValue' => 5,
        ];
    }
}
