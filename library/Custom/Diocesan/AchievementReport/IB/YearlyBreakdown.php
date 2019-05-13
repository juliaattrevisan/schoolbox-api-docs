<?php
/**
 * YearlyBreakdown
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\IB;

class YearlyBreakdown extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $externalId = $this->getExternalUserId();

        $query = "
            SELECT *
            FROM dbo.uvSchoolBoxIBResultsNEW
            WHERE ID = '{$externalId}'
            AND studentibflag = 1
            ORDER BY fileyear DESC, Term, Heading
        ";

        $results = $this->getDb()->getAll($query) ?: [];

        new \Alaress_Probe($results);

        return $results;
    }
}