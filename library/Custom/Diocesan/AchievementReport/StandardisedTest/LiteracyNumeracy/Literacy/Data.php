<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Payload;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy
 */
class Data extends Base
{

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
        $stack = (new Payload())->generate();

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
        $groupData = $this->groupData($result, 'maskseq');

        /* Mine out x-categories
         * There are two series data in here, displaying side by side on each x-axis tick
         */
        $template = $this->generateXCategories($groupData);

        $stack['x']['categories'] = '"' . implode('","', $template) . '"';

        /* Map AreaSeq values to series' $index; AreaSeq values can be any sequenced integer
         */
        $areaSeqMapping = $this->generateAreaSeqMapping($groupData);

        /* Mine out the stars' series
         */
        $stars = $this->generateStars($groupData, $areaSeqMapping, $template);

        $stack['series'][Payload::RED_SERIES] = $stars[Payload::RED_SERIES];
        $stack['series'][Payload::BLUE_SERIES] = $stars[Payload::BLUE_SERIES];

        /* Mine out the grey squares
         */
        $greys = $this->generateGreySquares($result, $areaSeqMapping, $template);

        $stack['series']['grey1'] = $greys[0];
        $stack['series']['grey2'] = $greys[1];
        $stack['series']['grey3'] = $greys[2];
        $stack['series']['grey4'] = $greys[3];

        return $stack;
    }


    /**
     * Mine out the stars as individual series
     *
     * @param array $groupData
     * @param array $areaSeqMapping
     * @param array $template
     * @return array
     */
    private function generateStars(array $groupData, array $areaSeqMapping, array $template): array
    {
        $red = $blue = $this->initialisePlaceholder($template);

        $index = 0;

        foreach ($groupData as $category => $vals) {
            $red[$index] = $vals[$areaSeqMapping[0]];
            $blue[$index+1] = $vals[$areaSeqMapping[1]];

            /* Move to the next group of data points
             */
            $index += self::NUM_SERIES;

            /* Might as well do data detection here
             * If the student has a row with maskseq != 0, they have data
             */
            if ($vals[$areaSeqMapping[0]] || $vals[$areaSeqMapping[1]]) {
                $this->hasData = true;
            }
        }

        return [
            Payload::RED_SERIES => $red,
            Payload::BLUE_SERIES => $blue
        ];
    }


    /**
     * Mine out the grey squares as individual series
     *
     * @param array $result
     * @param array $areaSeqMapping
     * @param array $template
     * @return array
     */
    private function generateGreySquares(array $result, array $areaSeqMapping, array $template): array
    {
        $greys = [];

        foreach (['Line1', 'Line2', 'Line3', 'Line4'] as $line) {
            $grey = $this->initialisePlaceholder($template);

            $groupData = $this->groupData($result, $line);
            $index = 0;

            foreach ($groupData as $category => $vals) {
                $grey[$index] = $vals[$areaSeqMapping[0]];
                $grey[$index+1] = $vals[$areaSeqMapping[1]];

                /* Move to the next group of data points
                 */
                $index += self::NUM_SERIES;
            }

            $greys[] = $grey;
        }

        return $greys;
    }


    /**
     * Find the AreaSeq sequence that we use to map red/blue/green data series
     *
     * @param array $placeholder
     * @return array
     */
    private function generateAreaSeqMapping(array $placeholder): array
    {
        return array_keys(array_values($placeholder)[0]);
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
