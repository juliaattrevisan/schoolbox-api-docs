<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\NCEA;

use Alaress\Database\Connector\IConnector;
use Custom\Dsfg\AchievementReport\Base as AchievementReportBase;
use Schoolbox\User;

abstract class Base extends AchievementReportBase
{
    const MAIN_DB_TABLE_NCEA = 'dbo.uvSchoolBoxNCEAResults';
    const MAIN_DB_TABLE_YEAR11 = 'dbo.uvSchoolBoxNCEAResultsforDataRefreshV2_Year11';

    /**
     * The main source table for data used in the NCEA report.
     * @var string
     */
    private string $dbTableName;

    /**
     * Constructor
     *
     * @param User        $user
     * @param array       $externalUser
     * @param IConnector  $db
     * @param string|null $year
     * @param string      $dbTableName
     */
    public function __construct(User $user, array $externalUser, IConnector $db, $year, string $dbTableName)
    {
        $this->dbTableName = $dbTableName;
        parent::__construct($user, $externalUser, $db, $year);
    }

    /**
     * @param string $dbTableName
     * @return string
     */
    protected function getDbTableName(string $dbTableName): string
    {
        return $this->dbTableName;
    }

    /**
     * Explorer, reporting!
     */
    protected function explorerReporting()
    {
        $explorer = $this->db->executeQuery("
            SELECT TOP 10 *
            FROM dbo.uvSchoolBoxNCEAResults
        ")->fetchAll();

//        $explorer = $this->db->getAll("
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
