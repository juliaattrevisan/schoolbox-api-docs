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
}