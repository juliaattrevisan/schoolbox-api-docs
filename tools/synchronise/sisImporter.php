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

Import user profile data from third party endpoint into local schema.
This is used by UPS to create user accounts.

This also imports timetable data from third party endpoint into local schema.

Usage: sisImporter.php [--file] [--perform]
        --perform   Persist changes
        --file      Optional path to a locally-cached xml file e.g. ../../../www/test.xml
                    Absence of this arg will cause the tool to access the api endpoint instead


HELP;
            exit;
        }

        /* Spawn schedular based on args
         *
         * Use Hourly frequency, taking advantage of the functional equivalence
         */
        $host = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key = $this->getConfig()->get('schooldata_import_endpoint_key');
        $frequency = Endpoint\Scheduler::HOURLY;
        $type = $this->getConfig()->get('schooldata_import_data_source');
        $file = $this->getOpt(self::OPT_LONG_FILE);

        /* Accept perform param from opts
         */
        $perform = $this->perform();

        /* Execute scheduler
         */
        $factory = new Endpoint\Factory($host, $key, $perform, $this->getLogger());

        (new Endpoint\Scheduler($frequency, $factory, $type, $file, $this->getLogger()))->run(time());

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
