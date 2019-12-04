#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\External\Importer\Endpoint;
use Zend\Uri\Exception\InvalidUriPartException;

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

        /* Spawn api based on args
         *
         * This gives you the cli choice of loading from an api endpoint
         * or a locally-cached xml file instead
         */
        $host = $this->getConfig()->get('external_ups_provider_batchxml_host');
        $key = $this->getConfig()->get('external_ups_provider_batchxml_key');

        /* TODO: Replace with config var
         */
        $type = Endpoint\Factory::ISAMS;

        /* TODO: Replace with config var
         */
        $frequency = Endpoint\Scheduler::HOURLY;

        /* This always perform updates
         */
        $factory = new Endpoint\Factory($host, $key, true, $this->getLogger());

        /* The time when the script ran
         */
        $now = time();

        /* Execute scheduler
         */
        (new Endpoint\Scheduler($frequency, $factory, $type, $this->getLogger()))->run($now);
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
