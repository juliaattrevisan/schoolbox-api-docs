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
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


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

        $stack = [
            'x' => [
                'categories' => []
            ],
            'series' => [
                'student' => [],
                'grey1' => [],
                'grey2' => [],
                'grey3' => [],
                'grey4' => [],
            ]
        ];

        foreach ($result as $row) {

            /* Accumulate the x-axis categories
             */
            $stack['x']['categories'][] = $row['xAxis'];

            /* The stars (what the student got)
             */
            $stack['series']['student'][] = $row['ActualMaskSeq'];

            /* Grey squares
             */
            $stack['series']['grey1'][] = $row['Line1'];
            $stack['series']['grey2'][] = $row['Line2'];
            $stack['series']['grey3'][] = $row['Line3'];
            $stack['series']['grey4'][] = $row['Line4'];
        }

        /* If the student has stars, they have values
         */
        if (\count($stack['series']['student'])) {
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
        return $this->findData()->hasData();
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }
}
