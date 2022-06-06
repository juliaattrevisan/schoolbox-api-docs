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
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {
        /**
         * @inheritdoc
         */
        public function run(): void
        {
            /** @var Rebuilder $cacheBuilder */
            $cacheBuilder = $this->getDi()->get('moduleCache.rebuilder');
            $cacheBuilder
                ->setLogger($this->getLogger())
                ->rebuild($this->getConfig()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));
        }
    },
    [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO],
);
$kernel->runApp();
