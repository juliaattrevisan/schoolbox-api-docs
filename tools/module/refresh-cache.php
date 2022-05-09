#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Loads or refreshes the route and module cache for this instance.
 *
 * This script must be run as part of the upgrade process. The ideal time for it
 * to be run is before an upgrade's SQL updates are run:
 * update scripts may require that routes and modules be defined, but route and
 * module definitions should not depend on SQL updates.
 */

use Alaress\Module\Cache\Rebuilder;
use Aura\Router\Router;
use Predis\Client;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {
        /**
         * @inheritdoc
         */
        public function run(): void
        {
            /** @var Router $router */
            /** @var Client $redis */
            $router       = $this->getDi()->get('router');
            $redis        = $this->getDi()->get('redis.storage');
            $cacheBuilder = new Rebuilder(
                $this->getDi(),
                $router,
                $redis,
                $this->getLogger(),
            );
            $cacheBuilder->rebuild($this->getConfig()->get('site_school_code'));
        }
    },
    [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO],
);
$kernel->runApp();
