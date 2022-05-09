<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\SchoolGrades;

class YearlyBreakdown extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'School Grades Summary';


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {

        /* Rearrange everything into a nice order
         *
         * $totals is a separate row for the table data
         */
        $yearStack = [];
        $totals = [];

        $preprocessed = $this->recombination();

        /* Try to accumulate values for term4; when term4 values
         * don't exist, use term2 values. This is what is meant by
         * "duplicate records" (misleading definition in specification)
         */
        foreach ($preprocessed as $year => $allCourses) {
            /* Initialise totals for letters
             */
            $totals[$year]['letters'] ??= array_fill_keys($this->getLetterRegistry(), 0);

            $totals[$year]['achievedGrades'] = 0;

            /* Choose which values to accumulate, from either
             * term4 or term2 (depending on which is available)
             *
             * We don't gather stats for Areas, just Courses
             */
            foreach ($allCourses as $courseName => $allAreas) {
                /* Initialise totals for letters
                 */
                $yearStack[$year][$courseName]['letters'] ??= array_fill_keys($this->getLetterRegistry(), 0);

                $yearStack[$year][$courseName]['achievedGrades'] = 0;

                /* Explore all the areas
                 */
                foreach ($allAreas as $currentArea => $allTerms) {
                    foreach (['term4', 'term3', 'term2', 'term1'] as $term) {
                        if ($allTerms[$term] != '') {
                            $data = $allTerms[$term];
                            $letter = $data['StrippedResult'];
                            $result = $allTerms[$term]['Result'];

                            $yearStack[$year][$courseName]['enteredGrades']++;
                            $totals[$year]['enteredGrades']++;

                            /* Ignore anything outside of the letter registry
                             */
                            if (in_array($letter, $this->getLetterRegistry(), false)) {
                                if (!$this->isProgressive($result)) {
                                    $yearStack[$year][$courseName]['letters'][$letter]++;
                                    $totals[$year]['letters'][$letter]++;

                                    /* Achieved Grades only accumulate when student got an
                                     * achievement letter
                                     */
                                    if (in_array($letter, $this->achievementLetterRegistry, false)) {
                                        $yearStack[$year][$courseName]['achievedGrades']++;
                                        $totals[$year]['achievedGrades']++;
                                    }
                                }
                            }

                            break;
                        }
                    }
                }
            }
        }

        /* Available Grades
         */
        foreach ($yearStack as $currentYear => $allCourses) {
            $totals[$currentYear]['availableGrades'] = 0;
            foreach ($allCourses as $courseName => $data) {
                $yearStack[$currentYear][$courseName]['availableGrades'] = $yearStack[$currentYear][$courseName]['enteredGrades'] - array_sum($yearStack[$currentYear][$courseName]['letters']);
                $totals[$currentYear]['availableGrades'] ??= 0;
                $totals[$currentYear]['availableGrades'] += $yearStack[$currentYear][$courseName]['availableGrades'];
            }
        }

        return [
            'yearStack' => $yearStack,
            'totals' => $totals
        ];
    }
}
