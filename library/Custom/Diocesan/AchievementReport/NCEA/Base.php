<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\NCEA;

use Custom\Diocesan\AchievementReport\Base as AchievementReportBase;

/**
 * Class Base
 *
 * @package Custom\Diocesan\AchievementReport\NCEA
 */
abstract class Base extends AchievementReportBase {

    /**
     * Explorer, reporting!
     */
    protected function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 10 *
            FROM dbo.uvSchoolBoxNCEAResults
        ");

//        $explorer = $this->getDb()->getAll("
//            SELECT
//                TABLE_CATALOG,
//                TABLE_SCHEMA,
//                TABLE_NAME,
//                TABLE_TYPE
//            FROM
//                INFORMATION_SCHEMA.TABLES
//            WHERE
//                TABLE_NAME LIKE '%uv%'
//        ");

        return $explorer;
    }

    /**
     * Decide whether this record should be ignored
     *
     * Exclude when Student Year = Current Year for Externally Assessed
     * Can't do this in the query; this data is actually conditionally accumulated.
     * Some places accumulate these values; other places don't.
     *
     * @param string $internalExternal
     * @param string $studentYear
     * @return bool
     */
    protected function includeData(string $internalExternal, string $studentYear): bool
    {
        $currentYearSelected = $studentYear === $this->getCurrentYear();
        $externallyAssessed = $internalExternal === 'EX';

        return !$externallyAssessed || !$currentYearSelected;
    }
}
