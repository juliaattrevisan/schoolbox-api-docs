#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Importer\Endpoint;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Calendar Importer
 *
 * Imports from a variety of sources and puts event data into the local tables:
 *   event
 *   event_campus
 *   event_folder
 *   event_role
 *   event_source
 *   event_user
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * --file
     *
     * Load xml contents from a file path specified by this arg
     */
    private const OPT_LONG_FILE = 'file:';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE
        ];
    }


    /**
     * Execute each strat in the composite
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import calendar event data from third party endpoint into local schema.

Usage: calendarImporter.php [--file] [--perform]
        --perform   Persist changes
        --file      Optional path to a locally-cached xml file e.g. ../../../www/test.xml
                    Absence of this arg will cause the tool to access the api endpoint instead


HELP;
            exit;
        }

        /* Spawn schedular based on args
         *
         * Use Hourly frequency, taking advantage of the functional equivalence
         *
         * (Custom Hours don't matter for the on-demand cli script; it should
         * always run when you execute this manually. The frequency is hardcoded
         * to Hourly to facilitate this behaviour; check ValidScheduleExecution
         * for the logic)
         */
        $host = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key = $this->getConfig()->get('schooldata_import_endpoint_key');
        $frequency = Endpoint\Scheduler::HOURLY;
        $customHours = [];
        $type = $this->getConfig()->get('schooldata_import_data_source');
        $file = $this->getOpt(self::OPT_LONG_FILE);

        /* You haven't specified a SUDS Data Source in Admin.
         */
        if (null === $type) {
            echo <<<NULLTYPE

You haven't specified a SUDS Data Source in Admin.


NULLTYPE;
            exit;
        }

        /* Accept perform param from opts
         */
        $perform = $this->perform();

        /* This identifier indicates that the two schedulers were part of this
         * script execution together
         */
        $runId = (new \Schoolbox\External\Connector\Log\Guid())->generate();

        /* Execute scheduler
         */
        $factory = new Endpoint\CalendarFactory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler(
            $frequency,
            $customHours,
            $factory,
            $type,
            $file,
            $this->getLogger(),
            \Schoolbox\External\Connector\Log\Mapper::getInstance(),
            $this->getDi()->get('sentry'),
        ))->run(time(), $runId, Endpoint\Scheduler::CALENDARIMPORTER_SCRIPT_NAME);

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
