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

        /**
         * @see \Schoolbox\Realtime\Pipe\PipeManager
         */
        $search = new Keyspace($redis, 'PHPREDIS_SESSION:*');
        $count = 0;
        foreach ($search as $key) {
            $count += $redis->del($key);
        }
        $this->getLogger()->debug("{$count} session records deleted.");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
