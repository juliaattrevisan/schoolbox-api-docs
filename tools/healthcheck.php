#!/usr/bin/env php
<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Admin\HealthCheck\Healthcheck;
use Schoolbox\Application\CliTool;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Perform external database permission checks
 */
$app = new class extends CliTool\BaseApp
{
    protected function getName(): string
    {
        return 'healthcheck';
    }

    protected function getDescription(): string
    {
        return 'Checks access permissions to database tables and stored procedures in the external db. You only get visual feedback when you use verbose mode.';
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $this->getLogger()->info('Running health check script');

        if (!$this->getConfig()->get('external_enable')) {
            $this->getLogger()->warning('External Database is currently disabled');
        }

        /** @var SchoolboxDatabaseFactory $factory */
        $factory = $this->getDi()->get('databaseFactory');
        /** @var IConnector $databaseExternal */
        $databaseExternal = $this->getDi()->get('databaseExternal');

        $healthCheck = new Healthcheck($databaseExternal, $factory, $this->getConfig(), $this->getLogger());
        $healthCheck->run();
        $results = $healthCheck->results();

        foreach ($results['errors'] ?? [] as $error) {
            $this->getLogger()->error($error);
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();
