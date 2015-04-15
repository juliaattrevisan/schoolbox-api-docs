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


class YearlyBreakdownTable extends Base {

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('School Grades Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($externalUserId);
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($externalUserId);

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