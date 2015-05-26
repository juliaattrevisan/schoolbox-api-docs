<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Custom\Diocesan\AchievementReport\Base as AchievementReportBase;

abstract class Base extends AchievementReportBase {

    /**
     * Explorer, reporting!
     */
    protected function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 10 *
            FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
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
     * @param $internalExternal
     * @param $studentYear
     * @return bool
     */
    protected function includeData($internalExternal, $studentYear) {
        $currentYearSelected = ($studentYear == $this->getCurrentYear());
        $externallyAssessed = ($internalExternal == 'EX');

        if (!$externallyAssessed || !$currentYearSelected) {
            return true;
        }

        return false;
    }

    /**
     * Determine if this result is progressive
     * (has square brackets around Result value)
     *
     * @param $result
     * @return bool
     */
    protected function isProgressive($result) {
        return preg_match('/\[['.implode('', $this->getLetterRegistry()).']\]/', $result);
    }
}