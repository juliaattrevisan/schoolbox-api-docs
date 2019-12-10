<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\GroupData;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Payload;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Squares;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Stars;

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
     * Find all the data for Literacy graph
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
        $groupData = (new GroupData())->generate($result, 'maskseq');

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

        $series = [Payload::RED_SERIES, Payload::BLUE_SERIES];

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
