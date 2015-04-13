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

use Custom\Diocesan\AchievementReport\Base;
use Alaress_Probe as Probe;
use Schoolbox\Collection;

class GradeSummaryTable extends Base {

    /**
     * Heading for this table
     *
     * @var string
     */
    protected $heading = 'Grade Summary';

    /**
     * The year for which to obtain data
     *
     * @var string
     */
    private $year = 0;

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));

        /* Set heading
         */
        $externalUser = $this->getExternalUser();

        $this->setHeading("Grade Summary for {$externalUser['StudentGiven1']} {$externalUser['StudentSurname']}");
    }

    /**
     * Obtain data
     */
    public function findData($externalUserId) {
        new Probe($this->getDb()->getAll("SELECT TOP 2 * FROM  SynergyOne.dbo.uvSchoolBoxSchoolGradeResults"));

        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($externalUserId);
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($externalUserId);

        /* Rearrange everything into a nice order
         */
        $finalStack = array();

        /* Do it for the current year
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $finalStack[$current['StudentYearLevel']]['enteredGrades'] += $current['num'];
        }

        /* And I suppose do it for all the previous years too
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $finalStack[$current['StudentYearLevel']]['enteredGrades'] += $current['num'];
        }

        new Probe($allEnteredGradesCurrentYear, $allEnteredGradesPreviousYear, $finalStack);

        return $finalStack;
    }

    /**
     * Get counts for Entered Grades for the current year
     * for a particular student
     *
     * @param $externalUserId
     * @return array|bool
     */
    private function findEnteredGradesCurrentYear($externalUserId) {
        $query = "
            SELECT DISTINCT (classcode + AssessAreaHeading),
                StudentYearLevel,
                StrippedResult,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND classyear = '{$this->getYear()}'
            GROUP BY (classcode + AssessAreaHeading), StudentYearLevel, StrippedResult
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
    private function findEnteredGradesPreviousYear($externalUserId) {
        $query = "
            SELECT DISTINCT (classcode + AssessAreaHeading),
                StudentYearLevel,
                StrippedResult,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND classyear < '{$this->getYear()}'
            AND Result != ''
            GROUP BY (classcode + AssessAreaHeading), StudentYearLevel, StrippedResult
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
}