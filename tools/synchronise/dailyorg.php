#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Settings\FieldType\SelectMulti\Renderer;
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

Specifically imports timetable-only data for profiles that already exist
in an instance (requires profile import to have run prior). This services
an automated Daily Org scenario at 8am when teachers decide they can't
come in to work, requiring schools to scramble for substitute teachers.

Due to the time sensitive nature of that, this only imports timetable data.
It doesn't import profile data or any calendar data.

This always performs updates; there is no dry-run mode for the cron task.

Usage: dailyorg.php


HELP;
            exit;
        }

        /* Spawn scheduler based on args
         *
         * This scheduler never takes a cached file as input source
         * Though maybe it should be able to do that...? It would allow people
         * to ftp stuff to a folder instead of providing an api endpoint
         */
        $host = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key = $this->getConfig()->get('schooldata_import_endpoint_key');

        /* Detect frequency. "select an option" and "none" mean we should just exit early
         */
        $frequency = trim((string)$this->getConfig()->get('schooldata_import_frequency'));

        if ('' === $frequency || Endpoint\Scheduler::NONE === $frequency) {
            $this->getLogger()->debug(sprintf(
                'Frequency set to "%s"; nothing to do. Exiting.',
                $frequency ?: 'select an option'
            ));

            exit(1);
        }

        /* A special check for exact timing
         */
        $dailyOrgFrequency = trim((string)$this->getConfig()->get('schooldata_import_dailyorg_time'));
        $currentTime = date('H:i');

        if ($currentTime !== $dailyOrgFrequency) {
            $this->getLogger()->debug(sprintf(
                'Daily Org Frequency set to %s but the current time is %s',
                $dailyOrgFrequency,
                $currentTime
            ));

            exit(1);
        }

        /* Forcibly mutate $frequency to be HOURLY to bypass all the usual timings so that
         * we can run this on demand (arguably there'd be a special const to denote that)
         */
        $frequency = Endpoint\Scheduler::HOURLY;

        $type        = $this->getConfig()->get('schooldata_import_data_source') ?: Endpoint\Factory::CSV;
        $customHours = [];
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

        /* This is needed to process liss data from storage files
         */
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

        /* This always perform updates
         */
        $perform = true;

        /* Used to shank a database instance
         */
        $randomMapper = UserMapper::getInstance();

        // Queries should not be time-limited here
        $randomMapper->getDatabase()->query('SET MAX_STATEMENT_TIME=0;');

        /* Remember the current instance's version number right before we import.
         * We encountered a scenario where an upgrade changed schema after the
         * shadow tables started, causing it to restore the old schema when it
         * finished, resulting in mysteriously-missing columns. We now detecct
         * version changes before shadow tables commit themselves at the end.
         */
        $version = $this->getVersion();

        /* This identifier indicates that the two schedulers were part of this
         * script execution together
         */
        $runId = (new Guid())->generate();

        /* Figure out which strats to import.
         *
         * (We only want the timetable one here.)
         */
        $currentStrat = ValidStrats::TIMETABLE;

        /* The time when the import ran
         * Could do REQUEST_TIME but this is good enough
         */
        $time = time();

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
            'scheduled daily org'
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

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
