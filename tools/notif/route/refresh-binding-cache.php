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
    new class extends CliTool\BaseApp
    {
        /**
         * If provided: run for this instance ID. Default to `INSTANCE_ID`.
         */
        private const OPT_LONG_INSTANCE = 'instance:';

        /**
         * If provided: run for this user only. Default to all users.
         */
        private const OPT_LONG_USER_ID  = 'userId:';

        protected function getName(): string
        {
            return 'refresh-binding-cache';
        }

        protected function getDescription(): string
        {
            return <<<DESC
                Calculates the set of bindings for a specified instance. It will:
                - calculate ALL bindings for the instance
                - cache the set, replacing any previously cached set
                - broadcast a SIGHUP (which advises `routed` instances to reload from cache)
                DESC;
        }

        protected function setOptions(): self
        {
            $this->addOption(null, self::OPT_LONG_INSTANCE, 'Instance ID to calculate bindings for; defaults to INSTANCE_ID');
            $this->addOption(null, self::OPT_LONG_USER_ID, 'Only calculate bindings for this user ID; defaults to all users');
            return $this;
        }

        protected function hasPerformMode(): bool
        {
            return false;
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
