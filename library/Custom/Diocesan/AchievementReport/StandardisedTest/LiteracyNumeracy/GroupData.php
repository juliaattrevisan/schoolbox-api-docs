<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base as StandardisedTestBase;

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
            $y = $row[$key] ?: StandardisedTestBase::DEFAULT_NULL_VALUE;

            /* Overwrite placeholder value
             */
            if (!isset($temp[$xAxis][$areaSeq]) || $temp[$xAxis][$areaSeq] === StandardisedTestBase::DEFAULT_NULL_VALUE) {
                $temp[$xAxis][$areaSeq] = $y;
            }
        }

        return $temp;
    }
}
