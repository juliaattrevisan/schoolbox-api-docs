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
     * The current year
     *
     * @var string
     */
    protected $currentYear = '';

    /**
     * A bunch of letters to obtain
     *
     * @var array
     */
    protected $letterRegistry = array('N', 'A', 'M', 'E');

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

    /* Getters/Setters
     */

    /**
     * Set letter registry
     *
     * @param array $letterRegistry
     */
    protected function setLetterRegistry($letterRegistry)
    {
        $this->letterRegistry = $letterRegistry;
    }

    /**
     * Get letter registry
     *
     * @return array
     */
    public function getLetterRegistry()
    {
        return $this->letterRegistry;
    }

    /**
     * Set current year
     *
     * @param string $currentYear
     */
    protected function setCurrentYear($currentYear)
    {
        $this->currentYear = $currentYear;
    }

    /**
     * Get current year
     *
     * @return string
     */
    protected function getCurrentYear()
    {
        return $this->currentYear;
    }
} 