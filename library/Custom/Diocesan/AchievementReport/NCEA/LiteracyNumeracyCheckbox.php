<?php
/**
 * LiteracyNumeracyCheckbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class LiteracyNumeracyCheckbox extends Base {
    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('Progress Grade Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        new Probe($this->explorerReporting());
    }


    /**
     * Explorer, reporting!
     */
    private function explorerReporting() {
//        $explorer = $this->getDb()->getAll("
//            SELECT TOP 50 *
//            FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
//        ");

        $explorer = $this->getDb()->getAll("
            SELECT
                TABLE_CATALOG,
                TABLE_SCHEMA,
                TABLE_NAME,
                TABLE_TYPE
            FROM
                INFORMATION_SCHEMA.TABLES
            WHERE
                TABLE_NAME LIKE '%uv%'
        ");

        return $explorer;
    }
}