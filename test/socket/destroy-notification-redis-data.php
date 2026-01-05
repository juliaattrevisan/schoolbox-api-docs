#!/usr/bin/env php
<?php

/**
 * This purges all notification cache data from Redis
 * It is used in the acceptance tests to ensure a clean slate
 */

declare(strict_types=1);

use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new class extends App {
        public function run(): void
        {
            $this->getLogger()->info('Removing Redis data');
            /** @var ClientInterface $redis */
            $redis = $this->getDi()->get('redis.storage');
            foreach ($redis->keys('msg:*') as $key) {
                $redis->del($key);
            }
            $this->getLogger()->info('Finished.');
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL
    ]
);
$kernel->runApp();
