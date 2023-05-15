#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
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
    /**
     * Does not touch any files, so user is not important
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** Hands off execution over to the App */
    public function run(): void
    {
        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis.storage');

        $search = new Keyspace($redis, 'remember' . '*');
        $count = 0;
        foreach ($search as $key) {
            $this->getLogger()->debug($key);
            $username = $redis->get($key);
            $query = $this->getDatabaseInternal()->executeQuery('select id from user where username = :username and (deleted_at is not null or enabled = 0)', ['username' => $username]);
            $res = $query->fetchColumn();
            if ($res) {
                $this->getLogger()->warning('REMOVE ' . $res);
            }
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();

