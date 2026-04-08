#!/usr/bin/env php
<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Ramsey\Uuid\Uuid;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\External\Importer\Endpoint\Factory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Profile/Timetable Importer
 *
 * Imports data from the sis into suds, a local data cache for profile and timetable data.
 * This is a landing zone for UPS data.
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
    public function getRequiredProcessUser(): string
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

Import user profile and timetable data from third party endpoint into local schema.
This is used by UPS to create user accounts and timetable information.

This also imports calendar events from a third party endpoint, if it exists

This always performs updates; there is no dry-run mode for the cron task.

Usage: userInitiated.php


HELP;
            exit;
        }

        /* Decide if there's anything to do
         */
        $importQueueMapper = \Schoolbox\External\Connector\ImportQueue\Mapper::getInstance();

        /* There's only one sync at a time, at most; if that exists, use the strats
         * that were specified.
         */
        $syncRequest = $importQueueMapper->findAll()->first();

        if (!$syncRequest) {
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
        $runId = Uuid::uuid4()->toString();

        /* Figure out which strats to import.
         *
         * If we have all the strats specified, then do a sub-optimisation that helps transaction
         * performance by doing profiles first, then timetables, then calendars
         */
        $splitStrats = [$syncRequest->getStrats()];

        /* A nerdier and sequence-independent way would be
         *
         * $strats = explode(',', $syncRequest->getStrats());
         * array_intersect(ValidStrats::STRATS_FULL, $strats) == ValidStrats::STRATS_FULL
         */
        if (implode(',', ValidStrats::STRATS_FULL) === $syncRequest->getStrats()) {
            if (\in_array($type, Factory::SPLITTABLE, true)) {
                /* Split the transaction in half for suds connectors that can actually do that
                 * Otherwise just import everything like it always did
                 */
                $splitStrats = [
                    implode(',', [ValidStrats::FAMILIES, ValidStrats::STAFF]),
                    ValidStrats::TIMETABLE,
                    ValidStrats::CALENDAR
                ];
            } else {
                /* Otherwise, for every other connector, just do all of them in one big go
                 */
                $splitStrats = [
                    implode(',', [ValidStrats::FAMILIES, ValidStrats::STAFF, ValidStrats::TIMETABLE]),
                    ValidStrats::CALENDAR,
                ];
            }
        }

        /* The time when both parts of the import ran
         * Could do REQUEST_TIME but this is good enough
         */
        $time = time();

        /* This is needed to process liss data from storage files
         */
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

        /* Remember the current instance's version number right before we import.
         * We encountered a scenario where an upgrade changed schema after the
         * shadow tables started, causing it to restore the old schema when it
         * finished, resulting in mysteriously-missing columns. We now detecct
         * version changes before shadow tables commit themselves at the end.
         */
        $version = $this->getVersion();

        foreach ($splitStrats as $currentStrat) {
            /* Calendar strat gets its own special treatment
             */
            if (ValidStrats::CALENDAR === $currentStrat) {
                /* Execute scheduler: calendar stuff
                 * Not all connectivities contain a calendar importer, just some
                 */
                $calendarFactory = new Endpoint\CalendarFactory(
                    $host,
                    $key,
                    $perform,
                    $version,
                    $this->getLogger()
                );

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
            } else {
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
                    $version,
                    $lissAdditiveMode,
                    $this->getLogger(),
                    [],
                    $validStrats,
                    $filesystemProxy
                );

                /* Importer strat gets suffixed onto the end
                 */
                $logTitle = sprintf(
                    '%s - (%s)',
                    Endpoint\Scheduler::SCHEDULER_SIS_SCRIPT_NAME,
                    $currentStrat
                );

                (new Endpoint\Scheduler(
                    $frequency,
                    $customHours,
                    $sisFactory,
                    $type,
                    $file,
                    $this->getLogger(),
                    \Schoolbox\External\Connector\Log\Mapper::getInstance(),
                    $this->getDi()->get('sentry'),
                ))->run($time, $runId, $logTitle);
            }
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
