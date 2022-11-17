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
     * The importers that put data into the cyclic grid
     * (including "-- select an option --")
     */
    public const CYCLIC_GRID = [ Factory::CSV, Factory::KAMAR, '' ];


    /**
     * $dtaSource corresponds to schooldata_import_data_source in config
     * (You could convert Factory::NAME to a more comprehensive hash lookup)
     *
     * @param string $dataSource
     * @return bool
     */
    public function isSatisfiedBy(string $dataSource): bool
    {
        return \in_array($dataSource, self::CYCLIC_GRID, true);
    }
}
