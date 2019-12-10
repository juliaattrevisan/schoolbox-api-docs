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


    private const RED_SERIES_AREASEQ = 2;
    private const BLUE_SERIES_AREASEQ = 3;


    /**
     * Find the series data for red/blue/green stars
     *
     * @param int    $areaSeq
     * @param string $externalId
     * @return array|bool
     */
    private function findSeries(int $areaSeq, string $externalId)
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
            AND StudentID='{$externalId}'
            AND AreaSeq = '{$areaSeq}'
            ORDER BY xAxisOrderSeq, Seq
        ";

        return $this->getDb()->getAll($query);
    }

    /**
     * Find all the data for Writing graph
     */
    public function findData()
    {

        $stack = [
            'x' => [
                'categories' => []
            ],
            'y' => [
                'stages' => '"' . implode('","', self::Y_STAGES) . '"',
                'levelGroups' => '"' . implode('","', self::Y_LEVEL_GROUPS) . '"',
            ],
            'series' => [
                'red' => [],
                'blue' => [],
                'grey1' => [],
                'grey2' => [],
                'grey3' => [],
                'grey4' => [],
            ]
        ];

        /* Series data for the red star
         */
        $redResult = $this->findSeries(self::RED_SERIES_AREASEQ, $this->externalUser['StudentID']);
new \Alaress_Probe($redResult);
        foreach ($redResult as $row) {
            $stack['series']['red'][] = $row['maskseq'] ?: 'null';

            /* Infer the x-categories from this stack
             */
            $stack['x']['categories'][] = $row['xAxis'];
        }

        /* Series data for the blue star
         */
        $blueResult = $this->findSeries(self::RED_SERIES_AREASEQ, $this->externalUser['StudentID']);

        foreach ($redResult as $row) {
            $stack['series']['blue'][] = $row['maskseq'] ?: 'null';
        }


        new \Alaress_Probe($stack);


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
