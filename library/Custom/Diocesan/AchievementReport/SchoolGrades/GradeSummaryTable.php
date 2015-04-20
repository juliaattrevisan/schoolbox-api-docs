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

class GradeSummaryTable extends Base {

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        // TODO: Implement setup() method.
    }

    /**
     * Obtain data
     */
    private function findData() {
        $query = "
            SELECT *
            FROM uvSchoolBoxSchoolGradesResults
            GROUP BY StudentYearLevel, StrippedResult
            ORDER BY StudentYearLevel DESC
        ";

        $stuff = $this->getDb()->getAll($query);

        new Probe($stuff);
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function getData()
    {
        // TODO: Implement getData() method.
        return 'output of getData()';
    }
}