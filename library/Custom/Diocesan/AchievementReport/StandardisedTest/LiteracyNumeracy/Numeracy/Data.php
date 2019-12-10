<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Numeracy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Payload;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Squares;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Stars;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Numeracy
 */
class Data extends Base
{

    /**
     * Indexes of each series
     */
    private const RED_SERIES = 'red';
    private const BLUE_SERIES = 'blue';
    private const GREEN_SERIES = 'green';


    /**
     * Number of series to look for here
     * These denote the number of x-categories per data group
     */
    private const NUM_SERIES = 3;


    /**
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


    /**
     * Find all the data for Numeracy graph
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
            WHERE code='LiteracyNumeracyY7_8_Mat'
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
        $stars = new Stars();
        $starsSeries = $stars->generate($groupData, $areaSeqMapping, $template, self::NUM_SERIES);
        $this->hasData = $stars->hasData();

        $series = [Payload::RED_SERIES, Payload::BLUE_SERIES, Payload::GREEN_SERIES];

        for ($i = 0; $i < self::NUM_SERIES; $i++) {
            $stack['series'][$series[$i]] = $starsSeries[$i];
        }

        /* Mine out the grey squares
         */
        $squares = new Squares();
        $squaresSeries = $squares->generate($result, $areaSeqMapping, $template, self::NUM_SERIES);

        $stack['series']['grey1'] = $squaresSeries[0];
        $stack['series']['grey2'] = $squaresSeries[1];
        $stack['series']['grey3'] = $squaresSeries[2];
        $stack['series']['grey4'] = $squaresSeries[3];

        return $stack;
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
            $template[] = $category;
        }

        return $template;
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
