<?php
/**
 * YearlyBreakdownTable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\SchoolGrades;

class YearlyBreakdown extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'School Grades Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
    }

    /**
     * Rearrange into a meaningful structure
     *
     * @return array
     */
    private function recombination() {

        /**
         * Rearrange everything into a nice order
         *
         * $yearStack is the table data
         */
        $yearStack = array();

        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($this->getExternalUserId());
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($this->getExternalUserId());

        /* Preprocess Current Year
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $studentYear = $current['Fileyear'];
            $courseName = $current['CourseName'];
            $assessArea = $current['AssessAreaHeading'];
            $term = "term{$current['Term']}";

            /* Initialise
             */
            if (!is_array($yearStack[$studentYear][$courseName][$assessArea])) {
                $yearStack[$studentYear][$courseName][$assessArea] = array(
                    'term2' => '',
                    'term4' => '',
                );
            }

            /* Assign values
             */
            $yearStack[$studentYear][$courseName][$assessArea][$term] = $current;
        }

        /* Preprocess Previous Years
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $studentYear = $current['Fileyear'];
            $courseName = $current['CourseName'];
            $assessArea = $current['AssessAreaHeading'];
            $term = "term{$current['Term']}";

            /* Initialise
             */
            if (!is_array($yearStack[$studentYear][$courseName][$assessArea])) {
                $yearStack[$studentYear][$courseName][$assessArea] = array(
                    'term2' => '',
                    'term4' => '',
                );
            }

            /* Assign values
             */
            $yearStack[$studentYear][$courseName][$assessArea][$term] = $current;
        }

        return $yearStack;
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {

        /**
         * Rearrange everything into a nice order
         *
         * $totals is a separate row for the table data
         */
        $yearStack = array();
        $totals = array();

        $preprocessed = $this->recombination();

        new \Alaress_Probe($preprocessed);

        /* Try to accumulate values for term4; when term4 values
         * don't exist, use term2 values. This is what is meant by
         * "duplicate records" (misleading definition in specification)
         */
        foreach ($preprocessed as $year => $courseName) {
            foreach ($courseName as $subject => $data) {
                if ($data['term4'] != '') {
                    $letter = $data['term4'];
                }
                elseif ($data['term2'] != '') {
                    $letter = $data['term2'];
                }

//                $yearStack[$year][$courseName]['enteredGrades']

            }
        }












//
//        /* Current year
//         */
//        foreach ($allEnteredGradesCurrentYear as $current) {
//            $yearStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];
//            $totals[$current['Fileyear']]['enteredGrades'] += $current['num'];
//

//            /* Initialise totals for letters
//             */
//            if (!is_array($totals[$current['Fileyear']]['letters'])) {
//                $totals[$current['Fileyear']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
//            }
//
//            /* Summate
//             */
//            if (!is_array($yearStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
//                $yearStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
//            }
//            else {
//                /* Ignore anything outside of the letter registry
//                 */
//                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
//                    $yearStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;
//                    $totals[$current['Fileyear']]['letters'][$current['StrippedResult']]++;
//                }
//            }
//        }






//
//
//
//
//
//
//        foreach ($allEnteredGradesPreviousYear as $current) {
//            $yearStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];
//            $totals[$current['Fileyear']]['enteredGrades'] += $current['num'];
//
//            /* Initialise totals for letters
//             */
//            if (!is_array($totals[$current['Fileyear']]['letters'])) {
//                $totals[$current['Fileyear']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
//            }
//
//            /* Summate
//             */
//            if (!is_array($yearStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
//                $yearStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
//            }
//            else {
//                /* Ignore anything outside of the letter registry
//                 */
//                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
//                    $yearStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;
//
//                    if (in_array($current['StrippedResult'], array('A', 'M', 'E'))) {
//                        $yearStack[$current['Fileyear']][$current['CourseName']]['achievedGrades']++;
//                        $totals[$current['Fileyear']]['achievedGrades']++;
//                    }
//
//                    $totals[$current['Fileyear']]['letters'][$current['StrippedResult']]++;
//                }
//            }
//        }
//
//        /* Available Grades
//         */
//        foreach ($yearStack as $currentYear => $allCourses) {
//            foreach ($allCourses as $courseName => $data) {
//                $yearStack[$currentYear][$courseName]['availableGrades'] = $yearStack[$currentYear][$courseName]['enteredGrades'] - array_sum($yearStack[$currentYear][$courseName]['letters']);
//                $totals[$currentYear]['availableGrades'] += $yearStack[$currentYear][$courseName]['availableGrades'];
//            }
//        }

        $finalStack = array(
            'yearStack' => $yearStack,
            'totals' => $totals
        );

        return $finalStack;
    }
}