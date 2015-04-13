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
        $allEnteredGrades = $this->findEnteredGrades($externalUserId);

        /* Rearrange everything into a nice order
         */
        $finalStack = array();

        foreach ($allEnteredGrades as $currentEnteredGrade) {
            $finalStack[$currentEnteredGrade['StudentYearLevel']][$currentEnteredGrade['StrippedResult']] = $currentEnteredGrade['num'];
        }

        return $finalStack;
    }

    /**
     * Get counts for Entered Grades across all the years
     * for a particular student
     *
     * @param $externalUserId
     * @return array|bool
     */
    private function findEnteredGrades($externalUserId) {
        $query = "
            SELECT DISTINCT
                StudentYearLevel,
                StrippedResult,
                COUNT(StrippedResult) AS num
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalUserId}'
            AND StrippedResult != ''
            GROUP BY StudentYearLevel, StrippedResult
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