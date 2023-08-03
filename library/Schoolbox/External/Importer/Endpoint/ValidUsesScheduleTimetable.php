<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use External\IConnector;

/**
 * Decide if the current SUDS connector uses the Schedule Timetable
 * (If it doesn't, then we presume usage of the cyclic grid)
 *
 * The cyclic grid usage in SUDS should eventually shrink to
 * just CSV and Empty String (this latter means you didn't select
 * a SUDS connector and we assume you mean to use the cyclic grid
 * with a live connector instead)
 */
class ValidUsesScheduleTimetable
{
    /**
     * These SUDS connectors use Schedule Timetable which is a calendar
     * representation of a timetable
     *
     * Everything outside this set uses (or is presumed to use)
     * the classical cyclic grid
     */
    private const SUDS_TIMETABLE = [
        Factory::ISAMS,
        Factory::SENTRAL,
        Factory::TASS_API,
        Factory::COMPASS,
        Factory::WONDE,
        Factory::VERACROSS
    ];


    /**
     * $dataSource corresponds to schooldata_import_data_source in config
     * (You could convert Factory::NAME to a more comprehensive hash lookup)
     *
     * @param string|null $externalDb
     * @param string|null $dataSource
     * @param string      $timetableView
     * @return bool
     */
    public function isSatisfiedBy(?string $externalDb, ?string $dataSource, string $timetableView): bool
    {
        /* You also have to be on the Schoolbox connector, not some other live connector
         * This can occur when you trade SUDS for a Live connector or vice versa,
         * predominantly in testing
         */
        if (IConnector::EXTERNAL_TYPE_SCHOOLBOX === $externalDb) {
            /* If you're using one of the Schedule Timetable SUDS connectors,
             * forcibly use the Schedule Timetable
             */
            if (\in_array($dataSource, self::SUDS_TIMETABLE, true)) {
                return true;
            }

            /* Or, if you're intentionally trying to show the Schedule Timetable
             * by having selected it in admin
             */
            if ($timetableView === \Schoolbox\External\Event\Model::TIMETABLE_VIEW_CALENDAR) {
                return true;
            }
        }

        return false;
    }
}
