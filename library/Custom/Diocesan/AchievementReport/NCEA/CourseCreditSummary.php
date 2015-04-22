<?php
/**
 * CourseCreditSummary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class CourseCreditSummary extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Course Credit Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setCurrentYear(date('Y'));
    }

    /**
     * Find data set
     *
     * @return array|bool
     */
    private function findCourseCreditSummary() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                CourseDescription,
                StrippedResult,
                EnteredCredits,
                ResultCredits
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}' AND
                WithdrawnFlag = 0 AND
                NOT (StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX') AND
                StudentYear = '{$this->getYear()}'
        ";

        return $this->getDb()->getAll($query);
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $finalStack = array();

        $allResults = $this->findCourseCreditSummary();

        foreach ($allResults as $current) {
            $letter = $current['StrippedResult'];
            $courseName = $current['CourseDescription'];
            $allLetters = array('A', 'M', 'E');

            if (!is_array($finalStack[$courseName])) {
                $finalStack[$courseName] = array(
                    'enteredCredits' => 0,
                    'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                    'total' => array(
                        'achieved' => 0,
                        'attempted' => 0
                    )
                );
            }

            $finalStack[$courseName]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack[$courseName]['letters'][$letter] += $current['ResultCredits'];

            /* Only accumulate N/A/M/E values
             */
            if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                $finalStack[$courseName]['total']['attempted'] += $current['ResultCredits'];
            }

            /* Only accumulate A/M/E values
             */
            if (in_array($current['StrippedResult'], $allLetters)) {
                $finalStack[$courseName]['total']['achieved'] += $current['ResultCredits'];
            }
        }

        return $finalStack;
    }
}