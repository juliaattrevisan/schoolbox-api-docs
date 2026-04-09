#!/usr/bin/env php
<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Ramsey\Uuid\Uuid;
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
        $timeout = (float)($this->getConfig()->get('schooldata_import_timeout') ?: Endpoint\Scheduler::DEFAULT_TIMEOUT);

        /* You haven't specified a SUDS Data Source in Admin.
         */
        if (null === $type) {
            echo <<<NULLTYPE

You haven't specified a SUDS Data Source in Admin.


NULLTYPE;
            exit;
        }

        /* SUDS imports can take a notoriously long time to complete based on sis etc.
         * and the instances could start up a new import before the existing one
         * has even finished. If you can't reserve this, then abort.
         */
        try {
            $lock = $this->acquireLock(Endpoint\Scheduler::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        /* SUDS imports sometimes take multiple hours to finish, even when they're
         * not supposed to; we would normally implement a $lock->refresh() instead
         * so that we're continually reserving an extra chunk of time while we're
         * still working. But that would mean shoving a $lock down into the Endpoint
         * Factory as an extra var, affecting the entire SUDS family.
         *
         * For now, we can presume a maximum execution time in case this process
         * continues for too long. This implementation needs to change if you
         * implement the $lock->refresh() as that has a very different meaning
         */
        $this->setMaxExecutionTime($timeout);

        /* Announce some basic details
         */
        $this->getLogger()->debug(sprintf(
            'Locking %s seconds for processing; max execution time is %s seconds',
            $timeout,
            $timeout
        ));

        /* Accept perform param from opts
         */
        $perform = $this->perform();

        $logMapper = \Schoolbox\External\Connector\Log\Mapper::getInstance();

        // Queries should not be time-limited here
        $logMapper->getDatabase()->executeDirectly('SET MAX_STATEMENT_TIME=0;');

        /* Remember the current instance's version number right before we import.
         * We encountered a scenario where an upgrade changed schema after the
         * shadow tables started, causing it to restore the old schema when it
         * finished, resulting in mysteriously-missing columns. We now detecct
         * version changes before shadow tables commit themselves at the end.
         */
        $version = $this->getVersion();

        /* This identifier indicates that the scheduler(s) were part of this
         * script execution together
         */
        $runId = Uuid::uuid4()->toString();

        /* Execute scheduler
         */
        $factory = new Endpoint\CalendarFactory(
            $host,
            $key,
            $perform,
            $version,
            $this->getLogger()
        );

        (new Endpoint\Scheduler(
            $frequency,
            $customHours,
            $factory,
            $type,
            $file,
            $this->getLogger(),
            $logMapper,
            $this->getDi()->get('sentry'),
        ))->run(time(), $runId, Endpoint\Scheduler::CALENDARIMPORTER_SCRIPT_NAME);

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
