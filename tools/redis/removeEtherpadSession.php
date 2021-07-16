#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Monolog\Logger;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

/**
 * Iterates over the the etherpad session keys and removes them from the system. These keys are temporary so they can
 * be disposed of safely
 */
$app = new class extends CliTool\App {
    /** @inheritDoc */
    public function run(): void
    {
        $this->getLogger()->info('Starting cleanup of etherpad sessions');

        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis');

        if (!$redis) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }

        $i=0;
        $search = new Keyspace($redis, 'sessionstorage:*');
        foreach ($search as $key) {
            $this->getLogger()->debug("Found sessionstorage object: $key");
            $redis->del($key);
            $i++;
        }

        $s = 0;
        $this->getLogger()->info("Setting ttl on sessions to ensure they expire");
        $search = new Keyspace($redis, 'session:*');
        foreach ($search as $key) {

            $contents = $redis->get($key);
            $ttl = $redis->ttl($key);
            $this->getLogger()->debug("Found session object: $key, $ttl: $contents");
            $contents = json_decode($contents,true);
            if(is_int($contents['validUntil']) && $contents['validUntil'] > 0) {
                $redis->expireAt($key, $contents['validUntil']);
                $s++;
            }
        }

        $this->getLogger()->info("Finished cleanup, removed $i session storage objects and set $s ttls on sessions");
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO]))->runApp();
