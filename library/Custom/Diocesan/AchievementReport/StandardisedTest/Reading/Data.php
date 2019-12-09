<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Reading;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\Reading
 */
class Data extends Base
{

    /**
     * Find all the data for Reading graph
     */
    public function findData()
    {

        $query = "
            SELECT
                xAxisOrderSeq,
                xAxis,
                Line1,
                Line2,
                Line3,
                Line4,
                ActualMaskSeq
            FROM uSchoolBoxReadingSummary
            WHERE StudentID='{$this->externalUser['StudentID']}'
            ORDER BY xAxisOrderSeq
        ";
        $result = $this->getDb()->getAll($query);

        return [];
    }


    /**
     * Find out if this student has data from any year
     *
     * @return bool
     */
    public function hasDataFromAnyYear(): bool
    {
        $query = "
            SELECT
                xAxisOrderSeq,
                xAxis,
                Line1,
                Line2,
                Line3,
                Line4,
                ActualMaskSeq
            FROM uSchoolBoxReadingSummary
            WHERE StudentID='{$this->externalUser['StudentID']}'
            ORDER BY xAxisOrderSeq
        ";

        $result = $this->getDb()->getOne($query);

        return (bool)$result;
    }
}
