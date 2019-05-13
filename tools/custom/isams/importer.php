#!/usr/bin/php
<?php
/**
 * ISAMS Importer
 *
 * Imports from an xml file and populates the following tables:
 *  user_profile
 *  user_relation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\Isams\Base\Importer\Composite;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\UserMapper;

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
         */
        $api = new Api($this->getConfig()->get('external_ups_provider_batchxml_host'), $this->getConfig()->get('external_ups_provider_batchxml_key'));

        $composite = new Composite(
            $this->getLogger(),
            $this->perform(),
            new Batchxml($api),
            UserMapper::getInstance(),
            UserProfileMapper::getInstance(),
            UserRelationMapper::getInstance()
        );

        $composite->import();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, null, $options))->runApp();
