#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\CampusMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\Isams\Base\Importer\Timetable;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use Schoolbox\UserMapper;
use Zend\Uri\Exception\InvalidUriPartException;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * ISAMS Timetable Importer
 *
 * Imports from an xml file and populates the following tables:
 *   class
 *   class_period
 *   class_user
 *   timetable_campus
 *   timetable_cycle
 *   timetable_period
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

Import Timetable data from ISAMS xml into local schema. This requires UPS to have created user
accounts in order to assign them to timetable classes.

Usage: profileImporter.php [--file] [--perform]
        --perform   Persist changes
        --file      Optional path to a locally-cached xml file e.g. ../../../www/test.xml
                    Absence of this arg will cause the tool to access the api endpoint instead


HELP;
            exit;
        }

        /* Spawn api based on args
         *
         * This gives you the cli choice of loading from an api endpoint
         * or a locally-cached xml file instead
         */
        $file = $this->getOpt(self::OPT_LONG_FILE);

        if ($file) {
            $this->getLogger()->info("Loading from locally-cached xml file: {$file}");
            $api = new MockApi($file);
        } else {
            $this->getLogger()->info('Accessing api endpoint');
            $api = new Api($this->getConfig()->get('external_ups_provider_batchxml_host'), $this->getConfig()->get('external_ups_provider_batchxml_key'));
        }

        $composite = new Timetable\Composite(
            $this->getLogger(),
            $this->perform(),
            new Batchxml($api),
            CampusMapper::getInstance(),
            UserMapper::getInstance(),
            TimetableCampusMapper::getInstance(),
            TimetableCycleMapper::getInstance(),
            TimetablePeriodMapper::getInstance(),
            ClassEntryMapper::getInstance(),
            ClassInvolvementMapper::getInstance(),
            ClassPeriodMapper::getInstance()
        );

        try {
            $composite->import();
        } catch (\RuntimeException $e) {
            $this->getLogger()->critical("Cannot access api endpoint: {$e->getMessage()}");
        } catch (InvalidUriPartException $e) {
            $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
