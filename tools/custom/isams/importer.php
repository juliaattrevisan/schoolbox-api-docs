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
use Schoolbox\External\Connector\Isams\Base\Importer\Composite;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App
{

    /**
     * Execute each strat in the composite
     */
    public function run(): void
    {
        $composite = new Composite(
            $this->getLogger(),
            new Batchxml(),
            UserMapper::getInstance(),
            UserProfileMapper::getInstance(),
            UserRelationMapper::getInstance()
        );

        $this->getDatabaseInternal()->beginTransaction();

        $composite->import();

        $this->getDatabaseInternal()->commit();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, null, $options))->runApp();
