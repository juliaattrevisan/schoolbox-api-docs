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
     * @var array
     */
    private $indexes = ['L1', 'L2', 'L3'];

    /**
     * Make an associative array using level indexes
     *
     * @return array
     */
    private function makeLevelArray(): array
    {
        return array_fill_keys($this->indexes, 0);
    }

    /**
     * @param array $data
     */
    public function aggregate(array $data)
    {
        /* Aggregation totals
         */
        $credits = [
            'gainedThisYear' => $this->makeLevelArray(),
            'broughtForward' => $this->makeLevelArray(),
            'total' => $this->makeLevelArray(),
        ];

        /* Group by subject name
         */
        $aggregatedData = [];

        /** @var array $allRecords */
        $allRecords = $data['records'];

        foreach ($allRecords as $current) {
            $subjectKey = $current['subjectName'];
            $levelKey = "L{$current['nceaLevel']}";

            if ($current['year'] === date('Y')) {

                /* Set up a new subject's information if it hasn't been encountered before
                 */
                if (!isset($aggregationData[$subjectKey])) {
                    $aggregatedData[$subjectKey] = [
                        'availableCredits' => 0,
                        'gainedCredits' => $this->makeLevelArray(),
                        'data' => []
                    ];
                }

                /* Store this record (it represents a line item in the tables) only if it's for the current year
                 */
                $aggregatedData[$subjectKey]['data'][] = $current;

                /* Augment and aggregate totals for each subject
                 */
                $aggregatedData[$subjectKey]['availableCredits'] += $current['nceaCredits'];
                $aggregatedData[$subjectKey]['gainedCredits'][$levelKey] += $current['creditsGained'];

                /* Also aggregate this summary total for the current year
                 */
                $credits['gainedThisYear'][$levelKey] += $current['creditsGained'];
            }
            else {

                /* Accumulate a total of credits that were obtained in a previous year
                 */
                $credits['broughtForward'][$levelKey] += $current['creditsGained'];
            }
        }

        /* Grand Total
         */
        foreach ($this->indexes as $levelKey) {
            $credits['total'][$levelKey] = $credits['gainedThisYear'][$levelKey] + $credits['broughtForward'][$levelKey];
        }

        new \Alaress_Probe($data, $aggregatedData, $credits);
    }
}