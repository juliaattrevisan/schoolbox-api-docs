<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\SchoolGrades;

use Custom\Diocesan\AchievementReport\Base as AchievementReportBase;

abstract class Base extends AchievementReportBase {

    /**
     * Get counts for Entered Grades for the current year
     * for a particular student
     *
     * @param $externalUserId
     * @return array
     */
    protected function findEnteredGradesCurrentYear($externalUserId) {
        $query = "
            SELECT
                classcode,
                StudentYearLevel,
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                StrippedResult
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = '{$externalUserId}'
                AND classyear = '{$this->getCurrentYear()}'
                AND Result != 'SNA'
            ORDER BY
                StudentYearLevel DESC,
                CourseName,
                AssessAreaHeading,
                Term DESC,
                StrippedResult DESC
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }

    /**
     * Get counts for Entered Grades for all previous years
     * for a particular student
     *
     * I'm not entirely sure why this needs to be separated but
     * whatever, I'm just going to follow the spreadsheet word for
     * word because who knows
     *
     * @param $externalUserId
     * @return array
     */
    protected function findEnteredGradesPreviousYear($externalUserId) {
        $query = "
            SELECT
                classcode,
                StudentYearLevel,
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                StrippedResult
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = '{$externalUserId}'
                AND classyear < '{$this->getCurrentYear()}'
                AND Result != ''
                AND Result != 'SNA'
            ORDER BY
                StudentYearLevel DESC,
                CourseName,
                AssessAreaHeading,
                Term DESC,
                StrippedResult DESC
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }

    /**
     * Explorer, reporting!
     */
    protected function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 10 *
            FROM SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
        ");

//        $explorer = $this->getDb()->getAll("
//            SELECT
//                TABLE_CATALOG,
//                TABLE_SCHEMA,
//                TABLE_NAME,
//                TABLE_TYPE
//            FROM
//                INFORMATION_SCHEMA.TABLES
//            WHERE
//                TABLE_NAME LIKE '%uv%'
//        ");

        return $explorer;
    }
}