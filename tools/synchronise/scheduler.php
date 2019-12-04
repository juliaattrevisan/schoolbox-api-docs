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
        $host = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key = $this->getConfig()->get('schooldata_import_endpoint_key');
        $frequency = $this->getConfig()->get('schooldata_import_frequency') ?? Endpoint\Scheduler::HOURLY;
        $type = $this->getConfig()->get('schooldata_import_data_source');
        $file = null;

        /* This always perform updates
         */
        $perform = true;

        /* Execute scheduler
         */
        $factory = new Endpoint\Factory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler($frequency, $factory, $type, $file, $this->getLogger()))->run(time());
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
