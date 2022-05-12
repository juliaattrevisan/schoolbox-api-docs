<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\SchoolGrades;

class GradeSummary extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Grade Summary';


    /**
     * Obtain data
     *
     * @return mixed
     */
    public function findData()
    {

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = [];

        /* Entered Grades
         */
        $preprocessed = $this->recombination();

        foreach ($preprocessed as $year => $allCourses) {
            foreach ($allCourses as $courseName => $allAreas) {
                foreach ($allAreas as $currentArea => $allTerms) {
                    foreach (['term4', 'term3', 'term2', 'term1'] as $term) {
                        if ($allTerms[$term] != '') {
                            $data = $allTerms[$term];

                            $studentYearLevel = $data['StudentYearLevel'];
                            $fileyear = $data['Fileyear'];
                            $letter = $data['StrippedResult'];

                            $finalStack[$studentYearLevel]['fileYear'] = $fileyear;
                            $finalStack[$studentYearLevel]['enteredGrades'] ??= 0;
                            $finalStack[$studentYearLevel]['enteredGrades']++;

                            /* Initialise totals for letters
                             */
                            if (!is_array($finalStack[$studentYearLevel]['letters'] ?? false)) {
                                $finalStack[$studentYearLevel]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
                            }

                            /* Exclude progressive grades for current year
                             */
                            if ($fileyear == $this->getCurrentYear()) {
                                if (!$this->isProgressive($data['Result'])) {
                                    $letter = $data['StrippedResult'];
                                } else {
                                    $letter = '';
                                }
                            }

                            /* Accumulate letter totals
                             */
                            if (in_array($letter, $this->getLetterRegistry(), false)) {
                                $finalStack[$studentYearLevel]['letters'][$letter]++;
                            }

                            break;
                        }
                    }
                }
            }
        }

        /* Available Grades
         */
        foreach ($finalStack as $currentYear => $data) {
            $finalStack[$currentYear]['availableGrades'] = $data['enteredGrades'] - array_sum($data['letters']);
        }

        return $finalStack;
    }
}
