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

class YearlyBreakdown extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Progress Grade Summary';

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
                ID = '{$this->getExternalUserId()}'
            ORDER BY
                classyear DESC,
                classcode DESC,
                Term DESC
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}