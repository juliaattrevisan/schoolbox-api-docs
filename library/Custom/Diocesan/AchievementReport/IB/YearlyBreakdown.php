<?php
/**
 * YearlyBreakdown
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\IB;

use Alaress_Probe as Probe;

class YearlyBreakdown extends Base {

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setHeading('Progress Grade Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allProgressGrades = $this->findAllProgressGrades();

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = array();

        foreach ($allProgressGrades as $current) {
            $courseName = trim($current['CourseName']);
            $classcode = trim($current['classcode']);
            $year = $current['classyear'];
            $term = $current['Term'];


            $finalStack[$year][$courseName]['classcode'] = $classcode;
            $finalStack[$year][$courseName]['allTerms'][$term]['level'] = $current['Level'];
            $finalStack[$year][$courseName]['allTerms'][$term]['progressGrade'] = $current['ProgressGrade'];
        }

        return $finalStack;
    }

    /**
     * Find all progress grades
     *
     * @return array
     */
    private function findAllProgressGrades() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                classyear,
                CourseName,
                classcode,
                Term,
                Level,
                ProgressGrade
            FROM
                SynergyOne.dbo.uvSchoolBoxIBResults
            WHERE
                ID = '{$externalUserId}'
            ORDER BY
                classyear DESC,
                classcode DESC,
                Term DESC
        ";

        return $this->getDb()->getAll($query);
    }

    /**
     * Explorer, reporting!
     */
    private function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 50 *
            FROM SynergyOne.dbo.uvSchoolBoxIBResults
        ");

        return $explorer;
    }
}