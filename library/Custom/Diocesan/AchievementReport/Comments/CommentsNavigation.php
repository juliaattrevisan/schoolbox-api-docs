<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\Comments;

/**
 * Class CommentsNavigation
 *
 * @package Custom\Diocesan\AchievementReport\Comments
 */
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
        $params = [
            'externalId' => $this->getExternalUserId()
        ];

        $query = '
            SELECT StudentYear
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = :externalId
            GROUP BY StudentYear
            ORDER BY StudentYear DESC
        ';

        $result = $this->getDb()->getAll($query, $params);

        $allYears = [];

        if (\is_array($result)) {
            foreach ($result as $current) {
                $allYears[] = $current['StudentYear'];
            }
        }

        return $allYears;
    }
}
