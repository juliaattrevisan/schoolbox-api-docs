<?php
/**
 * Aggregation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Stk\NCEA;

class Aggregation
{

    /**
     * @param array $data
     */
    public function aggregate(array $data)
    {
        /* Aggregation totals
         */
        $credits = [
            'gainedThisYear' => 0,
            'broughtForward' => 0,
            'total' => 0,
        ];

        /* Group by subject name
         */
        $aggregatedData = [];

        foreach ($data['records'] as $current) {
            $key = $current['subjectName'];

            /* Set up a new subject's information if it hasn't been encountered before
             */
            if (!isset($aggregationData[$key])) {
                $aggregatedData[$key] = [
                    'availableCredits' => 0,
                    'gainedCredits' => 0,
                    'data' => []
                ];
            }

            /* Store this record (it represents a line item in the tables)
             */
            $aggregatedData[$key]['data'][] = $current;

            /* Augment and aggregate totals for each subject
             */
            $aggregatedData[$key]['availableCredits'] += $current['nceaCredits'];
            $aggregatedData[$key]['gainedCredits'] += $current['creditsGained'];

            /* Also aggregate this summary total
             */
            $credits['gainedThisYear'] += $current['creditsGained'];
        }

        new \Alaress_Probe($data, $aggregatedData);
    }
}