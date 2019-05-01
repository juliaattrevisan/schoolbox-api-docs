<?php
/**
 * Comments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\Comments;

class Comments extends Base
{
    public function setup()
    {
    }


    /**
     * Get all the comments for a given user, ordered by year then term
     *
     * @return array
     */
    public function findData(): array
    {
        $query = "
            SELECT TOP 10 * 
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = '{$this->getExternalUserId()}'
            ORDER BY StudentYear, Term 
        ";

        $result = $this->getDb()->getAll($query);

        new \Alaress_Probe($result);


        return [];
    }
}