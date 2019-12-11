<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Mathematics;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\Base as StandardisedTestBase;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\Mathematics
 */
class Data extends Base
{

    /**
     * Y categories, Stages
     */
    private const Y_STAGES = [

        /* Offset node
         */
        'null',

        /* Pre-number, K1, K2
         */
        ' ', ' ', ' ',

        /* 1A
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 1B
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 2A
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 2B
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 3A
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 3B
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 4A
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* 4B
         */
        'Beginning',
        'Proficient',
        'Advanced',
    ];


    /**
     * Y categories, Level Groups
     */
    private const Y_LEVEL_GROUPS = [

        /* Offset node
         */
        'null',

        /* Pre-number, K1, K2
         */
        ' ', ' ', ' ',

        ' ',
        ' ',
        '1A',
        ' ',
        ' ',
        '1B',
        ' ',
        ' ',
        '2A',
        ' ',
        ' ',
        '2B',
        ' ',
        ' ',
        '3A',
        ' ',
        ' ',
        '3B',
        ' ',
        ' ',
        '4A',
        ' ',
        ' ',
        '4B',
    ];


    /**
     * Y categories, Lower Group
     * I can't see what this subsection is called
     *
     * It contains Pre Number, K1, K2 - but nothing defines this
     */
    private const Y_LOWER = [

        /* Offset node
         */
        'null',

        /* Pre-number, K1, K2
         */
        'Pre-Number',
        'K1',
        'K2',

        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
        ' ',
    ];


    /**
     * Find all the data for Writing graph
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
            FROM uSchoolBoxMathematicsSummary
            WHERE StudentID = :externalId
            ORDER BY xAxisOrderSeq
        ';

        $result = $this->db->getAll($query, $params);

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => json_encode(self::Y_STAGES),
                'levelGroups' => json_encode(self::Y_LEVEL_GROUPS),
                'lower' => json_encode(self::Y_LOWER),
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
