#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Monolog\Logger;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI tool for migrating access tokens which may have somehow been stored with
 * the disallowed : part separator.
 *
 * Only access tokens are migrated, as other token types don't last long enough
 * to be worth migrating.
 */
$app = new class extends CliTool\App {
    // Old and new key prefixes
    private const string OLD_KEY_PREFIX = 'oidcServer.' . INSTANCE_ID . ':access_token';
    private const string NEW_KEY_PREFIX = 'oidcServer.' . INSTANCE_ID . '.access_token';

    /**
     * Run the tool
     *
     * @return void
     */
    public function run(): void
    {
        /** @var ClientInterface $redisClient */
        $redisClient = $this->getDi()->get('redis.storage');

        $logger = $this->getLogger();

        $keysMigrated = [];

        // Find any keys with the old prefix, and migrate them to the new one.
        // This unavoidably requires a full table scan.
        $keyspace = new Keyspace($redisClient, self::OLD_KEY_PREFIX . '.*');
        foreach ($keyspace as $key) {
            [$val, $ttl] = $redisClient->transaction(
                function ($tx) use ($key) {
                    $tx->get($key);
                    $tx->ttl($key);
                }
            );

            $newKey = str_replace(self::OLD_KEY_PREFIX, self::NEW_KEY_PREFIX, $key);
            switch ($ttl) {
                case -2:
                    $logger->debug('Access token has expired: not migrating', ['oldKey' => $key, 'newKey' => $newKey]);
                    break;

                case -1:
                    $keysMigrated[$key] = $newKey;
                    $logger->debug('Migrating access token without expiry', ['oldKey' => $key, 'newKey' => $newKey]);
                    $redisClient->set($newKey, $val);
                    break;

                default:
                    $keysMigrated[$key] = $newKey;
                    $logger->debug(
                        'Migrating access token with expiry',
                        ['oldKey' => $key, 'newKey' => $newKey, 'ttl' => $ttl],
                    );
                    $redisClient->setex($newKey, $val, $ttl);
                    break;
            }
        }
        $logger->info(sprintf('Migration finished: %d keys migrated', count($keysMigrated)));
    }

    /* allow to be run as any user */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Logger::INFO], true))->runApp();
