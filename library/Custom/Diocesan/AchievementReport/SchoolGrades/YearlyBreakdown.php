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
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($this->getExternalUserId());
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($this->getExternalUserId());

        /**
         * Rearrange everything into a nice order
         */
        $yearStack = array();
        $totals = array();

        /* Current year
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $yearStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];
            $totals[$current['Fileyear']]['enteredGrades'] += $current['num'];

            /* Initialise totals for letters
             */
            if (!is_array($totals[$current['Fileyear']]['letters'])) {
                $totals[$current['Fileyear']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }

            /* Summate
             */
            if (!is_array($yearStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
                $yearStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }
            else {
                /* Ignore anything outside of the letter registry
                 */
                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                    $yearStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;
                    $totals[$current['Fileyear']]['letters'][$current['StrippedResult']]++;
                }
            }
        }

        /* Previous years
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $yearStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];
            $totals[$current['Fileyear']]['enteredGrades'] += $current['num'];

            /* Initialise totals for letters
             */
            if (!is_array($totals[$current['Fileyear']]['letters'])) {
                $totals[$current['Fileyear']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }

            /* Summate
             */
            if (!is_array($yearStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
                $yearStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }
            else {
                /* Ignore anything outside of the letter registry
                 */
                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                    $yearStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;

                    if (in_array($current['StrippedResult'], array('A', 'M', 'E'))) {
                        $yearStack[$current['Fileyear']][$current['CourseName']]['achievedGrades']++;
                        $totals[$current['Fileyear']]['achievedGrades']++;
                    }

                    $totals[$current['Fileyear']]['letters'][$current['StrippedResult']]++;
                }
            }
        }

        /* Available Grades
         */
        foreach ($yearStack as $currentYear => $allCourses) {
            foreach ($allCourses as $courseName => $data) {
                $yearStack[$currentYear][$courseName]['availableGrades'] = $yearStack[$currentYear][$courseName]['enteredGrades'] - array_sum($yearStack[$currentYear][$courseName]['letters']);
                $totals[$currentYear]['availableGrades'] += $yearStack[$currentYear][$courseName]['availableGrades'];
            }
        }

        $finalStack = array(
            'yearStack' => $yearStack,
            'totals' => $totals
        );

        return $finalStack;
    }
}