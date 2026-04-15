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
use Schoolbox\Oidc\CacheHelper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI tool for migrating refresh tokens which may have somehow been stored with
 * the disallowed : part separator.
 *
 * Only refresh tokens are migrated, as other token types don't last long enough
 * to be worth migrating.
 */
$app = new class extends CliTool\App {
    // Old and new key prefixes
    private const string KEY_PREFIX = 'oidcServer.' . INSTANCE_ID . ':' . CacheHelper::REFRESH_TOKEN_KEY;
    private const string OLD_KEY_PREFIX = self::KEY_PREFIX . self::OLD_PART_SEPARATOR;
    private const string NEW_KEY_PREFIX = self::KEY_PREFIX . self::NEW_PART_SEPARATOR;
    private const string OLD_PART_SEPARATOR = ':';
    private const string NEW_PART_SEPARATOR = '.';

    /**
     * Run the tool
     *
     * @return void
     */
    public function run(): void
    {
        $logger = $this->getLogger();
        $logger->info(
            sprintf(
                'Migrating refresh tokens in old key prefix "%s" to new prefix "%s"',
                self::OLD_KEY_PREFIX,
                self::NEW_KEY_PREFIX,
            ),
        );

        /** @var ClientInterface $redisClient */
        $redisClient  = $this->getDi()->get('redis.storage');
        $keysMigrated = [];

        // Find any keys with the old prefix, and migrate them to the new one.
        // This unavoidably requires a full table scan.
        $keyspace = new Keyspace($redisClient, self::OLD_KEY_PREFIX . '*');
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
                    $logger->debug(sprintf('Refresh token "%s" has expired: not migrating', $key));
                    break;

                case -1:
                    $keysMigrated[$key] = $newKey;
                    $logger->debug(sprintf('Migrating refresh token "%s" to "%s" without expiry', $key, $newKey));
                    $redisClient->set($newKey, $val);
                    break;

                default:
                    $keysMigrated[$key] = $newKey;
                    $logger->debug(sprintf('Migrating refresh token "%s" to "%s" with expiry %d', $key, $newKey, $ttl));
                    $redisClient->setex($newKey, $ttl, $val);
                    break;
            }
        }
        $logger->info(sprintf('Migration finished: %d refresh tokens migrated', count($keysMigrated)));
    }

    /* allow to be run as any user */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Logger::INFO], true))->runApp();
