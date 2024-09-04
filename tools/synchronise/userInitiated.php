#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Log\Guid;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\External\Importer\Endpoint\Factory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Profile/Timetable Importer
 *
 * Imports from an xml file and populates the following local sis tables:
 *   sis_user_profile
 *   sis_user_profile_staff
 *   sis_user_profile_student
 *   sis_user_profile_parent
 *   sis_user_profile_relation
 *   sis_class
 *   sis_class_period
 *   sis_class_user
 *   sis_timetable_campus
 *   sis_timetable_cycle
 *   sis_timetable_period
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }

    /**
     * Execute each strat in the composite
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

cron variant of sisImporter.php

Import user profile data from third party endpoint into local schema.
This is used by UPS to create user accounts.

This also imports timetable data from third party endpoint into local schema.

This also imports calendar events from a third party endpoint, if it exists

This always performs updates; there is no dry-run mode for the cron task.

Usage: userinitiated.php


HELP;
            exit;
        }

        /* Decide if there's anything to do
        */
        $importQueueMapper = \Schoolbox\External\Connector\ImportQueue\Mapper::getInstance();

        $hasRequested = $importQueueMapper->countAll();

        if (!$hasRequested) {
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        /* Spawn scheduler based on args
         *
         * This scheduler never takes a cached file as input source
         * Though maybe it should be able to do that...? It would allow people
         * to ftp stuff to a folder instead of providing an api endpoint
         */
        $host        = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key         = $this->getConfig()->get('schooldata_import_endpoint_key');
        $frequency   = Endpoint\Scheduler::HOURLY;
        $customHours = [];
        $type        = $this->getConfig()->get('schooldata_import_data_source');
        $file        = null;
        $timeout     = (float)($this->getConfig()->get('schooldata_import_timeout') ?: Endpoint\Scheduler::DEFAULT_TIMEOUT);

        /* LISS Additive Mode
         *
         * If on, liss data will be **added** to whatever the sis gave to us. We don't actually
         * know whether liss data should add, or replace, existing data; only the school can know
         * what they meant by having their liss data here.
         *
         * Turning it on lets us skip the Prepare and Cleanup strats from the liss factory.
         * It's off by default.
         */
        $lissAdditiveMode = (bool)$this->getConfig()->get('external_liss_additive_mode');

        /* Clear the sis_import_queue once we started, even if a lock was encountered.
         */
        \Schoolbox\External\Connector\ImportQueue\Mapper::getInstance()->truncate();

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

        /* This always perform updates
         */
        $perform = true;

        /* Used to shank a database instance
         */
        $randomMapper = UserMapper::getInstance();

        // Queries should not be time-limited here
        $randomMapper->getDatabase()->query('SET MAX_STATEMENT_TIME=0;');

        /* This identifier indicates that the two schedulers were part of this
         * script execution together
         */
        $runId = (new Guid())->generate();

        /* Split the transaction in half for suds connectors that can actually do that
         * Otherwise just import everything like it always did
         *
         * TODO this will be chosen via the ui somehow and provided here, in a future ticket
         */
        if (\in_array($type, [Factory::TASS_API, Factory::COMPASS], true)) {
            $splitStrats = [
                ValidStrats::FAMILIES . ',' . ValidStrats::STAFF,
                ValidStrats::TIMETABLE
            ];
        } else {
            $splitStrats = [''];
        }

        /* The time when both parts of the import ran
         * Could do REQUEST_TIME but this is good enough
         */
        $time = time();

        /* This is needed to process liss data from storage files
         */
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

        foreach ($splitStrats as $currentStrat) {
            /* Importer strats can be defined by cli args
             * null or empty string means "import everything"
             *
             * Otherwise, whatever you specified must fit within a known vocabulary
             * and works in conjunction with whatever watchlists you have in place
             *
             * scheduler always runs all strats
             * The cli option is hardcoded to empty str ''
             */
            $validStrats = new ValidStrats($currentStrat);

            /* Execute scheduler: sis stuff
             */
            $sisFactory = new Endpoint\Factory(
                $host,
                $key,
                $perform,
                $lissAdditiveMode,
                $this->getLogger(),
                [],
                $validStrats,
                $filesystemProxy
            );

            /* Importer strat gets suffixed onto the end
             */
            $subtitle = '';

            if ('' !== $currentStrat) {
                $subtitle = " ({$currentStrat})";
            }

            (new Endpoint\Scheduler(
                $frequency,
                $customHours,
                $sisFactory,
                $type,
                $file,
                $this->getLogger(),
                \Schoolbox\External\Connector\Log\Mapper::getInstance(),
                $this->getDi()->get('sentry'),
            ))->run($time, $runId, Endpoint\Scheduler::MANUAL_SIS_SCRIPT_NAME . $subtitle);
        }

        /* Execute scheduler: calendar stuff
         * Not all connectivities contain a calendar importer, just some
         */
        $calendarFactory = new Endpoint\CalendarFactory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler(
            $frequency,
            $customHours,
            $calendarFactory,
            $type,
            $file,
            $this->getLogger(),
            \Schoolbox\External\Connector\Log\Mapper::getInstance(),
            $this->getDi()->get('sentry'),
        ))->run($time, $runId, Endpoint\Scheduler::MANUAL_CALENDAR_SCRIPT_NAME);

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
