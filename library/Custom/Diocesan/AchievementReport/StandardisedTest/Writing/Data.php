<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Writing;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

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

        $query = "
            SELECT
                xAxisOrderSeq,
                xAxis,
                Line1,
                Line2,
                Line3,
                Line4,
                ActualMaskSeq
            FROM uSchoolBoxWritingSummary
            WHERE StudentID='{$this->externalUser['StudentID']}'
            ORDER BY xAxisOrderSeq
        ";

        $result = $this->getDb()->getAll($query);

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => '"' . implode('","', self::Y_STAGES) . '"',
                'curriculumLevels' => '"' . implode('","', self::Y_CURRICULUM_LEVELS) . '"'
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
