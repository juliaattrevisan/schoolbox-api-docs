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
     * Y categories incl. colour band
     */
    private const Y_CATEGORIES = [
        'Magenta',
        'Red',
        'Yellow',
        'Blue',
        'Green',
        'Orange',
        'Turquoise',
        'Purple',
        'Gold',
        '7.0 - 8.0',
        '7.5 - 8.5',
        '8.0 - 9.0',
        '8.5 - 9.5',
        '9.0 - 10.0',
        '9.5 - 10.5',
        '10.0 - 11.0',
        '10.5 - 11.5',
        '11.0 - 12.0',
        '11.5 - 12.5',
        '12.0 - 13.0',
        '12.5 - 13.5',
        '13.0 - 14.0',
        '13.5 - 14.5',
        '14.0 - 15.0',
        '14.5 - 15.5'
    ];


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
            'y' => [
                'categories' => '"' . implode('","', self::Y_CATEGORIES) . '"'
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
            $stack['x']['categories'][] = "'{$row['xAxis']}'";

            /* The stars (what the student got)
             */
            !$row['ActualMaskSeq'] ? $stack['series']['student'][] = 'null' : $stack['series']['student'][] = $row['ActualMaskSeq'];

            /* Grey squares
             */
            !$row['Line1'] ? $stack['series']['grey1'][] = 'null' : $stack['series']['grey1'][] = $row['Line1'];
            !$row['Line2'] ? $stack['series']['grey2'][] = 'null' : $stack['series']['grey2'][] = $row['Line2'];
            !$row['Line3'] ? $stack['series']['grey3'][] = 'null' : $stack['series']['grey3'][] = $row['Line3'];
            !$row['Line4'] ? $stack['series']['grey4'][] = 'null' : $stack['series']['grey4'][] = $row['Line4'];
        }

        $stack['x']['categories'] = implode(',', $stack['x']['categories']);

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
