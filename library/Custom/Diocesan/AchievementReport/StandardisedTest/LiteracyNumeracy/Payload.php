<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class Payload
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
class Payload
{

    /**
     * Indexes of each series
     */
    public const RED_SERIES = 'red';
    public const BLUE_SERIES = 'blue';
    public const GREEN_SERIES = 'green';


    /**
     * Y categories, Stages
     */
    private const Y_STAGES = [

        /* Offset node
         */
        'null',

        /* Level 1
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* Level 2
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* Level 3
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* Level 4
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* Level 5
         */
        'Beginning',
        'Proficient',
        'Advanced',

        /* Level 6
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

        ' ',
        ' ',
        'Level 1',
        ' ',
        ' ',
        'Level 2',
        ' ',
        ' ',
        'Level 3',
        ' ',
        ' ',
        'Level 4',
        ' ',
        ' ',
        'Level 5',
        ' ',
        ' ',
        'Level 6',
    ];


    /**
     * Generate a payload stack
     *
     * @return array
     */
    public function generate(): array
    {
        return [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => json_encode(self::Y_STAGES),
                'levelGroups' => json_encode(self::Y_LEVEL_GROUPS),
            ],
            'series' => [
                'red' => [],
                'blue' => [],
                'green' => [],
                'grey1' => [],
                'grey2' => [],
                'grey3' => [],
                'grey4' => [],
            ]
        ];
    }
}
