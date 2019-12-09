<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy
 */
class Data extends Base
{

    /**
     * Y categories, Stages
     */
    private const Y_STAGES = [

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
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


    /**
     * Find all the data for Writing graph
     */
    public function findData()
    {

        /* maskseq is what the student got
         * colour is the star colour (red for reading, blue for writing)
         * Seq is the order of the stars (red comes before blue)
         * Each of the xaxis rows needs to be grouped by AreaSeq
         */
        $query = "
            SELECT
                xAxis,
                AreaSeq,
                xAxisOrderSeq,
                Seq,
                colour,
                XAxisResultSeq,
                studentresultxaxis,
                Line1,
                Line2,
                Line3,
                Line4,
                maskseq
            FROM uvSchoolBoxLitNumSummary
            WHERE code='LiteracyNumeracyY7_8_Lit'
            AND StudentID='{$this->externalUser['StudentID']}'
            ORDER BY xAxisOrderSeq, Seq
        ";

        $result = $this->getDb()->getAll($query);

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => '"' . implode('","', self::Y_STAGES) . '"',
                'levelGroups' => '"' . implode('","', self::Y_LEVEL_GROUPS) . '"',
            ],
            'series' => [
                'red',
                'blue',
                'grey1' => [],
                'grey2' => [],
                'grey3' => [],
                'grey4' => [],
            ]
        ];

        $template = [];

        /* Find all the data points we actually need
         * This will be used as a series template
         */
        foreach ($result as $row) {
            $template[$row['xAxis']][$row['AreaSeq']] = 'null';
        }

        /* Mine out the categories and dupe out exactly two of them
         * One set for each star (there's only red and blue stars here)
         */
        $categories = array_keys($template);

        $xCategories = [];

        /* Yep, you read this right
         */
        foreach ($categories as $category) {
            $xCategories[] = $category;
            $xCategories[] = $category;
        }

        $redSeries = $template;
        $blueSeries = $template;

        /* Start manufacturing the star series
         */
        foreach ($result as $row) {
            if ($row['colour'] == 'Red') {
                $redSeries[$row['xAxis']][$row['AreaSeq']] = $row['maskseq'];
            } elseif ($row['colour'] == 'Rlue') {
                $blueSeries[$row['xAxis']][$row['AreaSeq']] = $row['maskseq'];
            }
        }

        new \Alaress_Probe($result, $template, $xCategories, $redSeries, $blueSeries);

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
