<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class GroupData
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
class GroupData
{

    /**
     * Generate a placeholder array, grouped by x-axis labels and AreaSeq value
     * Placeholder array defaults to string 'null', for highcharts
     *
     * @param array  $result
     * @param string $key
     * @return array
     */
    public function generate(array $result, string $key): array
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
