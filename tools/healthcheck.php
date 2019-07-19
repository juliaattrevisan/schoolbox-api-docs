#!/usr/bin/php
<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\CliTool;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Perform external database permission checks
 */
$app = new class extends CliTool\App
{

    public function run(): void
    {
        $this->getLogger()->info("Running health check script");

        if(!$this->getConfig()->get('external_enable')){
            $this->getLogger()->warning("External Database is currently disabled");
        }

        /** @var SchoolboxDatabaseFactory $factory */
        $factory = $this->getDi()->get('databaseFactory');
        /** @var IConnector $databaseExternal */
        $databaseExternal = $this->getDi()->get('databaseExternal');

        $healthCheck = new Healthcheck($databaseExternal, $factory, $this->getConfig());
        $healthCheck->run();
        $results = $healthCheck->results();

        $this->getLogger()->info($results['log']);

        foreach ($results['errors'] ?? [] as $error) {
            $this->getLogger()->error($error);
        }
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
