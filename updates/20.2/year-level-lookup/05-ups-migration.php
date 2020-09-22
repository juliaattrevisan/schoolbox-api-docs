<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    /**
     * Migrate ups_effect.arguments values for the Year user_property
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        /* Find a list of all the (lu_year_level.name, lu_year_level.id) tuples,
         * indexed by lu_year_level.name; we're gonna use that as a lookup
         */
        $yearLevelsStatement = $db->prepareQuery('
            SELECT id, name
            FROM lu_year_level
            ORDER BY name
        ');

        $yearLevelsStatement->execute();

        /* Set up lookup
         */
        $yearLevels = $yearLevelsStatement->fetchRowArray('name');

        /* Find all the UPS Effects for "year"
         */

        /* Get their string and unpack it
         */

        /* Everything inside [1] is a key-value pair
         */

            /* Replace the value which is the equivalent of lu_year_level.name,
             * with lu_year_level.id - anything that doesn't match should be given null
             * Such values are no longer determinate by us and the UPS admin needs to repair it
             */

        /* Save it back into the object
         */

        /* Repack the data payload and save it back
         */
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
