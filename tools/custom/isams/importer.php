#!/usr/bin/php
<?php
/**
 * ISAMS Importer
 *
 * Imports from an xml file and populates the following tables:
 *  user_profile
 *  user_relation
 *  class
 *  class_period
 *  class_user
 *  timetable_campus
 *  timetable_cycle
 *  timetable_period
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Importer\Composite;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\UserMapper;
use Zend\Uri\Exception\InvalidUriPartException;

require_once __DIR__ . '/../../../conf/core/require.php';

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
Import user_profile and user_relation data from ISAMS xml into local schema

Usage: importer.php

HELP;
            exit;
        }

        /* Spawn api
         * TODO: Swap with actual live connection when ready
         */
        $api = new MockApi($this->getConfig()->get('external_ups_provider_batchxml_host'), $this->getConfig()->get('external_ups_provider_batchxml_key'));

        $composite = new Composite(
            $this->getLogger(),
            $this->perform(),
            new Batchxml($api),
            UserMapper::getInstance(),
            UserProfileMapper::getInstance(),
            UserRelationMapper::getInstance(),
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
            $this->getLogger()->critical('Cannot access api endpoint.');
        } catch (InvalidUriPartException $e) {
            $this->getLogger()->critical('Cannot access api endpoint: bad url');
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->critical('Cannot access api endpoint: bad url');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, null, $options))->runApp();
