#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Predis\Collection\Iterator\Keyspace;
use Predis\ClientInterface;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Deletes all user sessions from Redis.
 */
$app = new class extends CliTool\App
{
    /** Hands off execution over to the App */
    public function run(): void
    {
        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis');

        // This script may run in two scenarios:
        // 1. Upgrade of existing instance (eg on-prem - possibly shared)
        // 2. Install of new instance (eg cloud)
        // In both cases, the redis instance may contain sessions from other Schoolbox instances.

        // Case 1. Upgrade of existing instance
        //         At this point in time, redis will contain sessions w/o an INSTANCE_ID.
        //         We want to deleted these sessions because they contain data that cannot be safely unserialised.
        // Cas2 2. Install of new instance
        //         Redis will contain sessions w/ an INSTANCE_ID, we don't want to delete these.
        // In both cases, we can delete all sessions w/o an INSTANCE_ID

        /** @see \Schoolbox\Realtime\Pipe\PipeManager */
        $search = new Keyspace($redis, 'PHPREDIS_SESSION:*');
        $count = 0;
        foreach ($search as $key) {
            if (preg_match('/PHPREDIS_SESSION:[^:]*:[^:]*/', $key)) {
                $this->getLogger()->debug("Skipping instance-specific session: '$key'");
                continue;
            }
            $this->getLogger()->info("Deleting instance-agnostic session: '$key'");
            $count += $redis->del($key);
        }
        $this->getLogger()->notice("{$count} session records deleted.");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
