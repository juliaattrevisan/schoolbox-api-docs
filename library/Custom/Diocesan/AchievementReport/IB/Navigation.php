<?php
/**
 * Navigation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Diocesan\AchievementReport\IB;

class Navigation extends Base
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
            SELECT fileyear
            FROM dbo.uvSchoolBoxIBResultsNEW
            WHERE ID = '{$externalId}'
            AND studentibflag = 1
            GROUP BY fileyear
            ORDER BY fileyear DESC
        ";

        $result = $this->getDb()->getAll($query);

        $allYears = [];

        if (\is_array($result)) {
            foreach ($result as $current) {
                $allYears[] = $current['fileyear'];
            }
        }

        return $allYears;
    }
}
