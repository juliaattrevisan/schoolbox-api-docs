<?php
/**
 * CommentsNavigation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\Comments;

class CommentsNavigation extends Base
{

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
            SELECT StudentYear
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = '{$this->getExternalUserId()}'
            GROUP BY StudentYear
            ORDER BY StudentYear DESC
        ";

        $result = $this->getDb()->getAll($query);

        $allYears = [];

        if (\is_array($result)) {
            foreach ($result as $current) {
                $allYears[] = $current['StudentYear'];
            }
        }

        return $allYears;
    }
}