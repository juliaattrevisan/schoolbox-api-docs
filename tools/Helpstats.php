<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Application\CliTool;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Queries exclusively the Schoolbox Help Database to fetch data required to fulful OLC KSC.
 * This data is used to populate our engagement database in influxDB and populate the graphs in monitor.schoolbox.com.au
 * Logging is piped to STDERR
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        /* @var IConnector $db */
        $db = $this->getDi()->get('databaseInternalRead');
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');
        // Only run for Schoolbox Help

        // Fetch a list of all existing unique School Codes, using a portion synergy_id as the source of truth

        // Fetch all the class possible school codes

        // Fetch all the current units in the OLC Course (course_spec 34)y

        // For each of these class codes I need to run a bunch of queries that only return results on pages that match 'school code + Unit Name'

        // Iterate through each school and then for each OLC Course unit fetch the number of people in that unit, the amount of 'starters', the amount of 'Stars' and the amount of 'Superstars'

        // Prepare the Data to send through to influx db

        // Send the Data
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runapp();
