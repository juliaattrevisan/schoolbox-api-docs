<?php
/**
 * GradeSummaryTable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\SchoolGrades;

class GradeSummary extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Grade Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
    }

    /**
     * Obtain data
     */
    public function findData() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($externalUserId);
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($externalUserId);

        /* All the counts for the letters
         */
        $allLetters = array();

        foreach ($this->getLetterRegistry() as $currentLetter) {
            $allLetters[] = $this->findLetter($externalUserId, $currentLetter);
        }

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = array();

        /* Do it for the current year
         * Initialise The Letters
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $finalStack[$current['StudentYearLevel']]['fileYear'] = $current['Fileyear'];
            $finalStack[$current['StudentYearLevel']]['enteredGrades'] += $current['num'];
            $finalStack[$current['StudentYearLevel']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
        }

        /* And I suppose do it for all the previous years too
         * Initialise The Letters
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $finalStack[$current['StudentYearLevel']]['fileYear'] = $current['Fileyear'];
            $finalStack[$current['StudentYearLevel']]['enteredGrades'] += $current['num'];
            $finalStack[$current['StudentYearLevel']]['letters'] = array_fill_keys($this->getLetterRegistry(), 0);
        }

        /* And for all the letters
         */
        foreach ($allLetters as $currentLetter) {
            if (is_array($currentLetter) && count($currentLetter)) {
                foreach ($currentLetter as $current) {
                    $finalStack[$current['StudentYearLevel']]['letters'][$current['StrippedResult']] += $current['num'];
                }
            }
        }

        /* Available Grades
         */
        foreach($finalStack as $currentYear => $data) {
            $finalStack[$currentYear]['availableGrades'] = $data['enteredGrades'] - array_sum($data['letters']);
        }

        return $finalStack;
    }

    /**
     * Find letters.
     *
     * N/A/M/E all have definitions, but what is D and S?
     *
     * @param $externalUserId
     * @param $letter
     * @return array|bool
     */
    private function findLetter($externalUserId, $letter) {
        $letter = strtoupper($letter);

        $query = "
            SELECT DISTINCT (classcode + ' ' + AssessAreaHeading),
                StudentYearLevel,
                StrippedResult,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND StrippedResult = '{$letter}'
            GROUP BY (classcode + ' ' + AssessAreaHeading), StudentYearLevel, StrippedResult
            ORDER BY StudentYearLevel DESC, StrippedResult DESC
        ";

        return $this->getDb()->getAll($query);
    }
}