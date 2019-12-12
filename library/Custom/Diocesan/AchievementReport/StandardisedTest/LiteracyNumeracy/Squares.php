<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base as StandardisedTestBase;

class Squares
{

    /**
     * Columns denoting grey squares' data
     */
    private const GREY_SQUARES = ['Line1', 'Line2', 'Line3', 'Line4'];


    /**
     * Mine out the grey squares as individual series
     *
     * @param array $result
     * @param array $areaSeqMapping
     * @param array $template
     * @param int   $numSeries
     * @return array
     */
    public function generate(array $result, array $areaSeqMapping, array $template, int $numSeries): array
    {
        $greys = [];

        foreach (self::GREY_SQUARES as $line) {
            $grey = $this->initialisePlaceholder($template);

            $groupData = (new GroupData())->generate($result, $line);
            $index = 0;

            foreach ($groupData as $category => $vals) {
                for ($i = 0; $i < $numSeries; $i++) {
                    $grey[$index+$i] = $vals[$areaSeqMapping[$i]];
                }

                /* Move to the next group of data points
                 */
                $index += $numSeries;
            }

            $greys[] = $grey;
        }

        return $greys;
    }


    /**
     * Initialise a stack of placeholder values for x categories
     *
     * @param array $template
     * @return array
     */
    private function initialisePlaceholder(array $template): array
    {
        return array_fill(0, \count($template), StandardisedTestBase::DEFAULT_NULL_VALUE);
    }
}
