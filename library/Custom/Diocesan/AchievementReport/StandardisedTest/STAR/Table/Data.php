<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Table;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Table
 */
class Data extends Base
{

    /**
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


    /**
     * I changed the SELECT aliases from the original, in the interest of posterity
     *
     * @return array
     */
    public function findData(): array
    {
        $query = "
            SELECT
                R.YEARLEVEL AS year,
                MAX(R.STAR) AS STAR
            FROM
            (
                select YearLevel,THISSTUDENTSTANINE,
                CASE WHEN IndividualStarTestStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS STAR
                from uvSchoolBoxPATSTARSummary
                where ThisStudentID='{$this->externalUser['StudentID']}' and IndividualStarTestStanineMarker<>0
            ) AS R
            GROUP BY R.YearLevel
            ORDER BY R.YearLevel DESC
        ";

        $result = $this->getDb()->getAll($query);

        /* Ensure everything is grouped by year
         */
        $stack = [];

        foreach ($result as $row) {
            $stack[$row['year']] = $row;
        }
        /* Decide if the table had any data
         */
        if (\count($stack)) {
            $this->hasData = true;
        }

        return $stack;
    }


    /**
     * Find out if this student has data from any year
     *
     * @return bool
     */
    public function hasDataFromAnyYear(): bool
    {
        $result = $this->findData();

        return (bool)\count($result);
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }
}
