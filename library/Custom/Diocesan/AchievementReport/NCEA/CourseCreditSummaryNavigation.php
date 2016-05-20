<?php
/**
 * CourseCreditSummaryNavigation
 *
 * Decide whether there is any data for Course Credit Summary
 * year-specific section
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

class CourseCreditSummaryNavigation extends Base {

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
    }

    /**
     * Returns data for twig rendering
     *
     * Finds all the years that have data
     *
     * @return mixed
     */
    public function findData()
    {
        $query = "
            SELECT
                StudentYear
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}' AND
                WithdrawnFlag = 0 AND
                NOT (StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX')
            GROUP BY StudentYear
            ORDER BY StudentYear DESC
        ";

        $result = $this->getDb()->getAll($query);

        $allYears = array();

        if (is_array($result)) {
            foreach ($result as $current) {
                $allYears[] = $current['StudentYear'];
            }
        }

        return $allYears;
    }
}