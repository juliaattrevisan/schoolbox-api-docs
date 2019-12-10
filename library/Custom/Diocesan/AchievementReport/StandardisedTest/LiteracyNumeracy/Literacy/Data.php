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
     * Indexes of each series
     */
    private const RED_SERIES = 'red';
    private const BLUE_SERIES = 'blue';


    /**
     * Number of series to look for here
     * These denote the number of x-categories per data group
     */
    private const NUM_SERIES = 2;


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

        /* Group everything by xAxis, AreaSeq
         * This removes dupes because multiple series are in this dataset
         */
        $temp = $this->groupData($result, 'maskseq');

        /* Mine out x-categories
         * There are two series data in here, displaying side by side on each x-axis tick
         */
        $template = $this->generateXCategories($temp);

        $stack['x']['categories'] = '"' . implode('","', $template) . '"';

        /* Build result sets
         */
        $red = $blue = $this->initialisePlaceholder($template);

        /* Map AreaSeq values to series' $index; AreaSeq values can be any sequenced integer
         */
        $areaSeqMapping = array_keys(array_values($temp)[0]);

        $index = 0;

        foreach ($temp as $category => $vals) {
            $red[$index] = $vals[$areaSeqMapping[0]];
            $blue[$index+1] = $vals[$areaSeqMapping[1]];

            /* Move to the next group of data points
             */
            $index += self::NUM_SERIES;
        }

        $stack['series'][self::RED_SERIES] = $red;
        $stack['series'][self::BLUE_SERIES] = $blue;

        /* If the student has stars, they have values - one of the series must have data
         */
        foreach ([self::RED_SERIES, self::BLUE_SERIES] as $series) {
            if (\count(array_filter($stack['series'][$series]))) {
                $this->hasData = true;
            }
        }
//
//        /* Mine out the grey squares
//         */
//        $greys = [];
//
//        foreach (['Line1', 'Line2', 'Line3', 'Line4'] as $line) {
//            $grey = array_fill(0, \count($template), 'null');
//
//            $temp = $this->groupData($result, $line);
//            $index = 0;
//
//            foreach ($temp as $category => $vals) {
//                $grey[$index] = $vals[$areaSeqMapping[0]];
//                $grey[$index+1] = $vals[$areaSeqMapping[1]];
//
//                /* Move to the next group of data points
//                 */
//                $index += self::NUM_SERIES;
//            }
//
//            $greys[] = $grey;
//        }
//
//        new \Alaress_Probe($result, $temp, $greys);
        return $stack;
    }


    /**
     * Initialise a stack of placeholder values for x categories
     *
     * @param array $template
     * @return array
     */
    private function initialisePlaceholder(array $template): array
    {
        return array_fill(0, \count($template), 'null');
    }


    /**
     * Generate a template of x categories
     *
     * @param array $placeholder
     * @return array
     */
    private function generateXCategories(array $placeholder): array
    {
        $names = array_keys($placeholder);
        $template = [];

        foreach ($names as $category) {
            $template[] = $category;
            $template[] = $category;
        }

        return $template;
    }


    /**
     * Generate a placeholder array, grouped by x-axis labels and AreaSeq value
     * Placeholder array defaults to string 'null', for highcharts
     *
     * @param array  $result
     * @param string $key
     * @return array
     */
    private function groupData(array $result, string $key): array
    {
        $temp = [];

        foreach ($result as $row) {
            $xAxis = $row['xAxis'];
            $areaSeq = $row['AreaSeq'];
            $y = $row[$key] ?: 'null';

            /* Overwrite placeholder value
             */
            if (!isset($temp[$xAxis][$areaSeq]) || $temp[$xAxis][$areaSeq] === 'null') {
                $temp[$xAxis][$areaSeq] = $y;
            }
        }

        return $temp;
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
