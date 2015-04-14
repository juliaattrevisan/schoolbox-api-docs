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
     * @param $externalUserId
     * @return mixed
     */
    public function findData($externalUserId)
    {
        // $explorer = $this->getDb()->getAll("SELECT TOP 2 * FROM  SynergyOne.dbo.uvSchoolBoxSchoolGradeResults");

        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($externalUserId);
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($externalUserId);

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = array();

        /* Current year
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $finalStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];

            if (!is_array($finalStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
                $finalStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }
            else {
                /* Ignore anything outside of the letter registry
                 */
                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                    $finalStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;
                }
            }
        }

        /* Previous years
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $finalStack[$current['Fileyear']][$current['CourseName']]['enteredGrades'] += $current['num'];

            if (!is_array($finalStack[$current['Fileyear']][$current['CourseName']]['letters'])) {
                $finalStack[$current['Fileyear']][$current['CourseName']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
            }
            else {
                /* Ignore anything outside of the letter registry
                 */
                if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                    $finalStack[$current['Fileyear']][$current['CourseName']]['letters'][$current['StrippedResult']]++;
                }
            }
        }

        /* Available Grades
         */
        foreach ($finalStack as $currentYear => $allCourses) {
            foreach ($allCourses as $courseName => $data) {
                $finalStack[$currentYear][$courseName]['availableGrades'] = $finalStack[$currentYear][$courseName]['enteredGrades'] - array_sum($finalStack[$currentYear][$courseName]['letters']);
            }
        }

        //new \Alaress_Probe($explorer, $allEnteredGradesCurrentYear, $allEnteredGradesPreviousYear, $finalStack);

        return $finalStack;
    }
}