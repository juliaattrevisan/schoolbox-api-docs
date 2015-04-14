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
     * The year for which to obtain data
     *
     * @var string
     */
    protected $year = 0;

    /**
     * A bunch of letters to obtain
     *
     * @var array
     */
    protected $letterRegistry = array('N', 'A', 'M', 'E');

    /**
     * Get counts for Entered Grades for the current year
     * for a particular student
     *
     * @param $externalUserId
     * @return array|bool
     */
    protected function findEnteredGradesCurrentYear($externalUserId) {
        $query = "
            SELECT DISTINCT
                (classcode + ' ' + AssessAreaHeading),
                classcode,
                AssessAreaHeading,
                CourseName,
                StudentYearLevel,
                StrippedResult,
                Fileyear,
                CourseName,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND classyear = '{$this->getYear()}'
            GROUP BY
                (classcode + ' ' + AssessAreaHeading),
                classcode,
                AssessAreaHeading,
                CourseName,
                StudentYearLevel,
                StrippedResult,
                Fileyear
            ORDER BY StudentYearLevel DESC, StrippedResult DESC
        ";

        return $this->getDb()->getAll($query);
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
     * @return array|bool
     */
    protected function findEnteredGradesPreviousYear($externalUserId) {
        $query = "
            SELECT DISTINCT
                (classcode + ' ' + AssessAreaHeading),
                classcode,
                AssessAreaHeading,
                CourseName,
                StudentYearLevel,
                StrippedResult,
                Fileyear,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND classyear < '{$this->getYear()}'
            AND Result != ''
            GROUP BY
                (classcode + ' ' + AssessAreaHeading),
                classcode,
                AssessAreaHeading,
                CourseName,
                StudentYearLevel,
                StrippedResult,
                Fileyear
            ORDER BY StudentYearLevel DESC, StrippedResult DESC
        ";

        return $this->getDb()->getAll($query);
    }

    /* Getters/Setters
     */

    /**
     * @param string $year
     */
    public function setYear($year)
    {
        $this->year = $year;
    }

    /**
     * @return string
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * Set letter registry
     *
     * @param array $letterRegistry
     */
    protected function setLetterRegistry($letterRegistry)
    {
        $this->letterRegistry = $letterRegistry;
    }

    /**
     * Get letter registry
     *
     * @return array
     */
    public function getLetterRegistry()
    {
        return $this->letterRegistry;
    }
} 