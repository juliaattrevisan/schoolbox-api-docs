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
    }

    /**
     * Obtain data
     */
    public function findData($externalStudentId) {
        $this->debug();

        $query = "
            SELECT TOP 2 *
            FROM SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE classyear = '{$this->getYear()}'
        ";

        $schema = $this->getDb()->getAll($query);

        /* Ignore all StrippedResults when they are empty string
         */
        $query = "
            SELECT
                StudentYearLevel,
                StrippedResult,
                COUNT(*) AS num
            FROM SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$externalStudentId}'
            AND StrippedResult != ''
            GROUP BY StudentYearLevel, StrippedResult
            ORDER BY StudentYearLevel DESC, StrippedResult DESC
        ";

        $allRecords = $this->getDb()->getAll($query);

        /* Rearrange everything into a nice order
         */
        $finalStack = new Collection();

        foreach ($allRecords as $currentRecord) {
            $newRecord = array();
            $newRecord[$currentRecord['StudentYearLevel']][$currentRecord['StrippedResult']] = $currentRecord['num'];

            $finalStack->add($newRecord);
        }

        new Probe($schema, $allRecords, $finalStack);

        return $finalStack;
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