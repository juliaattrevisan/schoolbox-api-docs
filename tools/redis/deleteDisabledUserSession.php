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
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Bulk deletes user session and remember me for all deleted or disabled users from Redis.
 */
$app = new class extends CliTool\App {
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
        $this->deprecationNotice("SA-19334");

        if ($this->perform()) {
            $this->getLogger()->info('Deleting remember me and sessions.');
        } else {
            $this->getLogger()->info('Dry run mode. Re-run this script with -p to perform.');
        }

        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis.storage');

        // Get username of active users.
        $query         = $this->getDatabaseInternal()->executeQuery(
            'SELECT `username` FROM `user` WHERE `deleted_at` IS NULL AND `enabled` = 1'
        );
        $activeUsers = $query->fetchColumnArray('username');

        // remember me
        $search = new Keyspace($redis, sprintf(\Schoolbox\Auth\Remember\Redis::REDIS_KEY_FORMAT, '*', '*'));
        foreach ($search as $key) {
            $username = $redis->get($key);
            if (!$username) {
                $this->getLogger()->warning('Deleting empty key: ' . $key);
                if ($this->perform()) {
                    $redis->del($key);
                }
                continue;
            }
            $this->getLogger()->debug("$key : $username");
            if (!in_array($username, $activeUsers)) {
                $this->getLogger()->warning("Deleting remember me for user: {$username} {$key}");
                if ($this->perform()) {
                    $redis->del($key);
                }
            }
        }

        // session
        $search = new Keyspace($redis, PipeManager::REDIS_SESSION_PREFIX . '*');
        // Decode the session data.
        // Unfortunately a session must be started in order to do this:
        session_start();
        foreach ($search as $key) {
            $redisSession = $redis->get($key);
            if (!$redisSession) {
                $this->getLogger()->warning('Deleting empty key: ' . $key);
                if ($this->perform()) {
                    $redis->del($key);
                }
                continue;
            }
            session_decode($redisSession);
            /** @var User|null $user */
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                $this->getLogger()->warning('Deleting empty key: ' . $key);
                if ($this->perform()) {
                    $redis->del($key);
                }
                continue;
            }
            $this->getLogger()->debug("$key : {$user->getId()} {$user->getUsername()}");
            if (!in_array($user->getUsername(), $activeUsers)) {
                $this->getLogger()->warning("Deleting session for user {$user->getId()}: {$user->getUsername()} {$key}");
                $redis->del($key);
            }
        }

        $this->deprecationNotice("SA-19334");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
