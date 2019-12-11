<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Mathematics;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

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

        /* Pre-number, K1, K2
         */
        null, null, null,

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

        /* Pre-number, K1, K2
         */
        null, null, null,

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
                'stages' => '"' . implode('","', self::Y_STAGES) . '"',
                'levelGroups' => '"' . implode('","', self::Y_LEVEL_GROUPS) . '"',
                'lower' => '"' . implode('","', self::Y_LOWER) . '"',
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
}
