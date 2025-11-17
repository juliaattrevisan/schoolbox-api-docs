#!/usr/bin/env php
<?php

/**
 *
 * This script is solely responsible for calculating the set of bindings FOR A SPECIFIED INSTANCE.
 * It will:
 *  - calculate ALL bindings for the instance
 *  - cache the set, replacing any previously cached set
 *  - broadcast a SIGHUP (which advises `routed` instances to reload from cache)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {
        /**
         * If provided: run for this instance ID. Default to `INSTANCE_ID`.
         */
        private const OPT_LONG_INSTANCE = 'instance:';

        /**
         * If provided: run for this user only. Default to all users.
         */
        private const OPT_LONG_USER_ID  = 'userId:';

        /**
         * @inheritdoc
         */
        public function getLongOpts(): array
        {
            return [
                self::OPT_LONG_INSTANCE,
                self::OPT_LONG_USER_ID,
            ];
        }

        /**
         * @inheritdoc
         */
        public function run(): void
        {
            $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;
            $userId     = $this->getOpt(self::OPT_LONG_USER_ID);

            /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
            $cache = $this->getDi()->get('subscriptionCache');
            $cache->setLog($this->getLogger());

            if ($userId) {
                $cache->cacheUsers($instanceId, [$userId]);
            } else {
                $cache->cacheAllUsers($instanceId);
            }
        }

        /**
         * May run as root or not.
         */
        public function runAsRoot(): ?bool
        {
            return null;
        }
    },
    [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO],
    true,
);
$kernel->runApp();
