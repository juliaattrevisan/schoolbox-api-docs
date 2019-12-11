<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Reading;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\Base as StandardisedTestBase;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\Reading
 */
class Data extends Base
{

    /**
     * Y categories, colour band
     */
    private const Y_COLOURWHEEL = [

        /* Offset node
         */
        'null',

        'Magenta',
        'Red',
        'Yellow',
        'Blue',
        'Green',
        'Orange',
        'Turquoise',
        'Purple',
        'Gold',
        ' ', ' ', ' ', ' ', ' ',
        ' ', ' ', ' ', ' ', ' ',
        ' ', ' ', ' ', ' ', ' ',
        ' '
    ];


    /**
     * Y categories, probe age
     */
    private const Y_PROBEAGE = [

        /* Offset node
         */
        'null',

        ' ', ' ', ' ',
        ' ', ' ', ' ',
        ' ', ' ', ' ',
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
     * Find all the data for Reading graph
     */
    public function findData()
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = '
            SELECT
                xAxisOrderSeq,
                xAxis,
                Line1,
                Line2,
                Line3,
                Line4,
                ActualMaskSeq
            FROM uSchoolBoxReadingSummary
            WHERE StudentID = :externalId
            ORDER BY xAxisOrderSeq
        ';

        $result = $this->db->getAll($query, $params);

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'colourWheel' => json_encode(self::Y_COLOURWHEEL),
                'probeAge' => json_encode(self::Y_PROBEAGE),
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
            !$row['ActualMaskSeq'] ? $stack['series']['student'][] = StandardisedTestBase::DEFAULT_NULL_VALUE : $stack['series']['student'][] = $row['ActualMaskSeq'];

            /* Grey squares
             */
            !$row['Line1'] ? $stack['series']['grey1'][] = StandardisedTestBase::DEFAULT_NULL_VALUE : $stack['series']['grey1'][] = $row['Line1'];
            !$row['Line2'] ? $stack['series']['grey2'][] = StandardisedTestBase::DEFAULT_NULL_VALUE : $stack['series']['grey2'][] = $row['Line2'];
            !$row['Line3'] ? $stack['series']['grey3'][] = StandardisedTestBase::DEFAULT_NULL_VALUE : $stack['series']['grey3'][] = $row['Line3'];
            !$row['Line4'] ? $stack['series']['grey4'][] = StandardisedTestBase::DEFAULT_NULL_VALUE : $stack['series']['grey4'][] = $row['Line4'];
        }

        $stack['x']['categories'] = json_encode($stack['x']['categories']);

        /* If the student has stars, they have values
         */
        if (\count($stack['series']['student'])) {
            $this->hasData = true;
        }

        return $stack;
    }
}
