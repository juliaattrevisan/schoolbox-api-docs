<?php
/**
 * EntriesAndResults
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class EntriesAndResults extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Entries And Results';

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
        $finalStack = array();

        $allResults = $this->findEntries();

        foreach ($allResults as $current) {
            $courseName = $current['CourseDescription'];

            /* Trim empty teachers out for course teachers
             */
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher1'];
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher2'];
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher3'];
            $finalStack[$courseName]['allTeachers'] = array_values(array_unique(array_filter($finalStack[$courseName]['allTeachers'])));

            /* Misc data
             */
            $finalStack[$courseName]['allEntries'][] = array(
                'NCEAStandardNumber' => $current['NCEAStandardNumber'],
                'InternalorExternalAssessment' => $current['InternalorExternalAssessment'],
                'StandardTitle' => $current['StandardTitle'],
                'Level' => $current['Level'],
                'EnteredCredits' => $current['EnteredCredits'],
                'StrippedResult' => $current['StrippedResult'],
                'ResultBrackets' => $current['ResultBrackets']
            );
        }

        return $finalStack;
    }

    /**
     * Find data about Entries and Results
     *
     * @return array
     */
    private function findEntries() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                CourseDescription,
                ClassTeacher1,
                ClassTeacher2,
                ClassTeacher3,
                NCEAStandardNumber,
                InternalorExternalAssessment,
                StandardTitle,
                Level,
                EnteredCredits,
                StrippedResult,
                CAST(CASE WHEN StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX' THEN 1 ELSE 0 END AS bit) AS ResultBrackets
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}' AND
                WithdrawnFlag = 0
                AND StudentYear = '{$this->getYear()}'
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}