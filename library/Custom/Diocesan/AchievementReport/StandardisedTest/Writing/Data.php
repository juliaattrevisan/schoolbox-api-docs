<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Writing;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\Base as StandardisedTestBase;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\Writing
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

        'Beginning',
        'Proficient',
        'Advanced',
        'Beginning',
        'Proficient',
        'Advanced',
        'Beginning',
        'Proficient',
        'Advanced',
        'Beginning',
        'Proficient',
        'Advanced',
    ];


    /**
     * Y categories, Curriculum Levels
     */
    private const Y_CURRICULUM_LEVELS = [

        /* Offset node
         */
        'null',

        'a ',
        'b ',
        'Curriculum Level 1',
        'c ',
        'd ',
        'Curriculum Level 2',
        'e ',
        'f ',
        'Curriculum Level 3',
        'g ',
        'h ',
        'Curriculum Level 4',
    ];


    /**
     * Find all the data for Writing graph
     */
    public function findData()
    {
        $params = [
            'externalId' =>$this->getExternalUserId(),
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
            FROM uSchoolBoxWritingSummary
            WHERE StudentID = :externalId
            ORDER BY xAxisOrderSeq
        ';

        $result = $this->db->getAll($query, $params);

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => self::Y_STAGES,
                'curriculumLevels' => self::Y_CURRICULUM_LEVELS,
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

        /* If the student has stars, they have values
         */
        if (\count($stack['series']['student'])) {
            $this->hasData = true;
        }

        return $stack;
    }
}
