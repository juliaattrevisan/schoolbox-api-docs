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
use Schoolbox\External\Importer\Endpoint;
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

Usage: scheduler.php


HELP;
            exit;
        }

        /* Spawn scheduler based on args
         *
         * This scheduler never takes a cached file as input source
         * Though maybe it should be able to do that...? It would allow people
         * to ftp stuff to a folder instead of providing an api endpoint
         */
        $host        = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key         = $this->getConfig()->get('schooldata_import_endpoint_key');

        /* Detect frequency. "select an option" and "none" mean we should just exit early
         */
        $frequency = (string)$this->getConfig()->get('schooldata_import_frequency');

        if ('' === $frequency || Endpoint\Scheduler::NONE === $frequency) {
            $this->getLogger()->debug(sprintf(
                'Frequency set to "%s"; nothing to do. Exiting.',
                $frequency ?: 'select an option'
            ));
            exit(1);
        }

        $type        = $this->getConfig()->get('schooldata_import_data_source') ?: Endpoint\Factory::CSV;
        $customHours = explode(Renderer::VALUE_DELIMITER, $this->getConfig()->get('schooldata_import_managed_hours') ?? '');
        $file        = null;
        $timeout     = (float)($this->getConfig()->get('schooldata_import_timeout') ?: Endpoint\Scheduler::DEFAULT_TIMEOUT);
        // This affects only Tass API
        $includePhoto = true;

        /* SUDS imports can take a notoriously long time to complete based on sis etc.
         * and the instances could start up a new import before the existing one
         * has even finished. If you can't reserve this, then abort.
         */
        $lock = $this->acquireLock(Endpoint\Scheduler::LOCK_NAME, $timeout);

        if (!$lock->isAcquired()) {
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

        /* This identifier indicates that the two schedulers were part of this
         * script execution together
         */
        $runId = (new \Schoolbox\External\Connector\Log\Guid())->generate();

        /* Execute scheduler: sis stuff
         */
        $sisFactory = new Endpoint\Factory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler(
            $randomMapper->getDatabase(),
            $frequency,
            $customHours,
            $sisFactory,
            $type,
            $file,
            $this->getLogger(),
            \Schoolbox\External\Connector\Log\Mapper::getInstance()
        ))->run(time(), $runId, Endpoint\Scheduler::SCHEDULER_SIS_SCRIPT_NAME);

        /* Execute scheduler: calendar stuff
         * Not all connectivities contain a calendar importer, just some
         */
        $calendarFactory = new Endpoint\CalendarFactory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler(
            $randomMapper->getDatabase(),
            $frequency,
            $customHours,
            $calendarFactory,
            $type,
            $file,
            $this->getLogger(),
            \Schoolbox\External\Connector\Log\Mapper::getInstance()
        ))->run(time(), $runId, Endpoint\Scheduler::SCHEDULER_CALENDAR_SCRIPT_NAME);

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
