<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class Base
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
abstract class Base extends \Custom\Diocesan\AchievementReport\StandardisedTest\Base
{

    /**
     * Find all the data for Literacy/Numeracy graphs
     *
     * @param string $code
     * @param array  $series
     * @return array
     */
    public function findDataForSeries(string $code, array $series): array
    {
        $numSeries = \count($series);

        /* Placeholder stack
         */
        $stack = (new Payload())->generate();

        $params = [
            'code' => $code,
            'externalId' => $this->getExternalUserId(),
        ];

        /* maskseq is what the student got
         * colour is the star colour (red for reading, blue for writing)
         * Seq is the order of the stars (red comes before blue)
         * Each of the xaxis rows needs to be grouped by AreaSeq
         */
        $query = '
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
            WHERE code = :code
            AND StudentID = :externalId
            ORDER BY xAxisOrderSeq, Seq
        ';

        $result = $this->db->getAll($query, $params);

        /* No data? Exit early
         */
        if (!\count($result)) {
            return $stack;
        }

        /* Group everything by xAxis, AreaSeq
         * This removes dupes because multiple series are in this dataset
         */
        $groupData = (new GroupData())->generate($result, 'maskseq');

        /* Mine out x-categories
         * There are two series data in here, displaying side by side on each x-axis tick
         */
        $template = (new Categories())->generate($groupData, $numSeries);

        $stack['x']['categories'] = $template;

        /* Map AreaSeq values to series' $index; AreaSeq values can be any sequenced integer
         */
        $areaSeqMapping = (new AreaSeqMapping())->generate($groupData);

        /* Mine out the stars' series
         */
        $stars = new Stars();
        $starsSeries = $stars->generate($groupData, $areaSeqMapping, $template, $numSeries);
        $this->hasData = $stars->hasData();

        for ($i = 0; $i < $numSeries; $i++) {
            $stack['series'][$series[$i]] = $starsSeries[$i];
        }

        /* Mine out the grey squares
         */
        $squares = new Squares();
        $squaresSeries = $squares->generate($result, $areaSeqMapping, $template, $numSeries);

        $stack['series']['grey1'] = $squaresSeries[0];
        $stack['series']['grey2'] = $squaresSeries[1];
        $stack['series']['grey3'] = $squaresSeries[2];
        $stack['series']['grey4'] = $squaresSeries[3];

        return $stack;
    }
}
