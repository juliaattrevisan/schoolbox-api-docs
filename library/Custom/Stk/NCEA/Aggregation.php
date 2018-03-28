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
     * @var array
     */
    private $achievements = ['A', 'M', 'E'];

    /**
     * Make an associative array using level indexes
     *
     * Start at null so highcharts knows to ignore a level when there's no data in it (all null values)
     * Otherwise it renders a stacked barchart of length 0 which looks ugly
     *
     * @param int $default
     * @return array
     */
    private function makeLevelArray($default = null): array
    {
        return array_fill_keys($this->indexes, $default);
    }

    /**
     * Make an associative array using level indexes and prefill it with
     * the initials of achievements
     *
     * @return array
     */
    private function makeAchievementArray(): array
    {
        $levels = $this->makeLevelArray();

        return array_fill_keys($this->achievements, $levels);
    }

    /**
     * Make an aggregation for the given $year
     **
     * @param array $data
     * @param int   $year
     * @return array
     */
    public function aggregate(array $data, int $year): array
    {
        /* Aggregation totals
         */
        $credits = [
            'gainedThisYear' => $this->makeLevelArray(),
            'broughtForward' => $this->makeLevelArray(),
            'total' => $this->makeLevelArray(),
        ];

        /* Data for Graph
         */
        $graph = $this->makeAchievementArray();

        /* Group by subject name
         */
        $aggregatedData = [];

        /** @var array $allRecords */
        $allRecords = $data['records'];

        foreach ($allRecords as $current) {
            $subjectKey = $current['subjectSort'];
            $levelKey = "L{$current['nceaLevel']}";
            $gradeKey = $current['grade'];

            /* Data for Graph: only care about the achievements listed in $this->achievements
             * All others are ignored
             *
             * Accumulate from all records obtained in Acquisition (it's not year-specific)
             */
            if (\in_array($gradeKey, $this->achievements, true)) {
                $graph[$gradeKey][$levelKey] += $current['creditsGained'];
            }

            /* Data for Tables
             */
            if ($current['year'] === $year) {

                /* Set up a new subject's information if it hasn't been encountered before
                 */
                if (!isset($aggregatedData[$subjectKey])) {
                    $aggregatedData[$subjectKey] = [
                        'availableCredits' => 0,
                        'gainedCredits' => $this->makeLevelArray(),
                        'data' => []
                    ];
                }

                /* Store this record (it represents a line item in the tables) only if it's for the current year
                 * Augment it with a Levels array, because this makes it nicer in twig town
                 */
                $augmented = array_merge($current, $this->makeLevelArray());
                $augmented[$levelKey] = $current['creditsGained'];

                $aggregatedData[$subjectKey]['data'][] = $augmented;

                /* Augment and aggregate totals for each subject
                 */
                $aggregatedData[$subjectKey]['availableCredits'] += $current['nceaCredits'];
                $aggregatedData[$subjectKey]['gainedCredits'][$levelKey] += $current['creditsGained'];

                /* Also aggregate this summary total for the current year
                 */
                $credits['gainedThisYear'][$levelKey] += $current['creditsGained'];
            }
            elseif ($current['year'] < $year) {

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

        return [
            'meta' => $data['meta'],
            'graph' => $graph,
            'records' => $aggregatedData,
            'credits' => $credits,
        ];
    }
}