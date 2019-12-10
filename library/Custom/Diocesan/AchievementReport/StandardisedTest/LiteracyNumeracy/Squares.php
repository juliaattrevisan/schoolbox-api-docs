<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

class Squares
{

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

        foreach (['Line1', 'Line2', 'Line3', 'Line4'] as $line) {
            $grey = $this->initialisePlaceholder($template);

            $groupData = $this->groupData($result, $line);
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
        return array_fill(0, \count($template), 'null');
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
}
