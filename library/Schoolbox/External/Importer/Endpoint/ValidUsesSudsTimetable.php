<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

/**
 * Decide if the current SUDS connector uses the SUDS Timetable
 * (If it doesn't, then we presume usage of the cyclic grid)
 *
 * The cyclic grid usage in SUDS should eventually shrink to
 * just CSV and Empty String (this latter means you didn't select
 * a SUDS connector and we assume you mean to use the cyclic grid
 * with a live connector instead)
 */
class ValidUsesSudsTimetable
{
    /**
     * These SUDS connectors use SUDS Timetable which is a calendar
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
        Factory::LISS,
        Factory::VERACROSS
    ];


    /**
     * $dataSource corresponds to schooldata_import_data_source in config
     * (You could convert Factory::NAME to a more comprehensive hash lookup)
     *
     * @param string $lissUsername
     * @param string $lissPassword
     * @param string $dataSource
     * @return bool
     */
    public function isSatisfiedBy(string $lissUsername, string $lissPassword, string $dataSource): bool
    {
        $username = trim($lissUsername);
        $password = trim($lissPassword);

        /* If your liss credentials are meaningful, use Schedule Timetable
         * SUDS liss now populates Schedule Timetable
         */
        if ('' !== $username && '' !== $password) {
            return true;
        }

        /* Remember if we've chosen a SUDS Connector that supports Schedule Timetable
         * (Only some SUDS Connectors populate Schedule Timetable)
         */
        $isScheduleTimetable = \in_array($dataSource, self::SUDS_TIMETABLE, true);

        /* No liss credentials provided;
         * Might be using Cyclic, might be using Schedule; dig deeper,
         * because the sis might fully populate the timetable by itself without liss
         */
        if ('' === $username && '' === $password) {
            return $isScheduleTimetable;
        }

        /* Only one of the liss configuration pieces provided;
         * there's no way this could have worked, but is an oddball scenario
         * where schools haven't finished configuring properly
         *
         * This, and every other case: use the cyclic grid
         */
        return false;
    }
}
