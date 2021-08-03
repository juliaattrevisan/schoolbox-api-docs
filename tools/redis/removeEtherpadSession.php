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
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

/**
 * TODO - IMPORTANT - DO NOT RUN FOR ALL INSTANCES IN PARALLEL ON SERVER - YOU WILL OVERLOAD REDIS SERVER!
 *
 * Iterates over the etherpad session keys and removes them from the system.
 * These keys are temporary, so they can be disposed of safely
 */
$app = new class extends CliTool\App {

    private const PROGRESS_INCREMENTS = 10000;

    private int $countSessionStorageDeleted = 0;
    private int $countUeberDBDeleted        = 0;
    private int $countSessionTTLsUpdated    = 0;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null; // Doesn't matter.
    }


    /** @inheritDoc */
    public function run(): void
    {
        $redis = $this->getRedisService();
        $this->deleteSessionStorage($redis);
        $this->deleteUeberDB($redis);
        $this->setSessionTTLs($redis);
        $this->printResults();
    }

    /**
     * @return ClientInterface
     */
    private function getRedisService(): ClientInterface
    {
        $redis = $this->getDi()->get('redis');
        if (!$redis instanceof ClientInterface) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        return $redis;
    }


    /**
     * Deletes top-level etherpad session storage objects
     *
     * @param ClientInterface $redis
     */
    private function deleteSessionStorage(ClientInterface $redis): void
    {
        $this->getLogger()->notice('Starting cleanup of etherpad sessions');
        $search = new Keyspace($redis, 'sessionstorage:*');
        foreach ($search as $key) {
            // Show some progress every N keys...
            if ($this->countSessionStorageDeleted % self::PROGRESS_INCREMENTS === 0 && $this->countSessionStorageDeleted) {
                $this->getLogger()->info("$this->countSessionStorageDeleted...");
            }
            $this->getLogger()->debug("Removing sessionstorage object: $key");
            $this->countSessionStorageDeleted++;
            if ($this->perform()) {
                $redis->del($key);
            }
        }
    }


    /**
     * Deletes all members of the 'ueberDB:keys:sessionstorage' set
     *
     * @param ClientInterface $redis
     */
    private function deleteUeberDB(ClientInterface $redis): void
    {
        $this->getLogger()->notice("Starting cleanup of etherpad ueberDB keys. Set size: {$redis->scard('ueberDB:keys:sessionstorage')}");

        $itr = new Predis\Collection\Iterator\SetKey($redis, 'ueberDB:keys:sessionstorage');
        foreach ($itr as $key) {
            // Show some progress every N keys
            if ($this->countUeberDBDeleted % self::PROGRESS_INCREMENTS === 0 && $this->countUeberDBDeleted) {
                $this->getLogger()->info("$this->countUeberDBDeleted...");
            }
            $this->getLogger()->debug("Removing sessionstorage from key list: $key");
            $this->countUeberDBDeleted++;
            if ($this->perform()) {
                $redis->srem('ueberDB:keys:sessionstorage', $key);
            }
        }
    }


    /**
     * Ensure the TTL inside the etherpad session is applied to the redis key
     *
     * @param ClientInterface $redis
     */
    private function setSessionTTLs(ClientInterface $redis): void
    {
        $this->getLogger()->info("Setting ttl on sessions to ensure they expire");
        $search = new Keyspace($redis, 'session:*');
        $i      = 0;
        foreach ($search as $key) {
            // Show some progress every N/10 keys
            if ($i % (self::PROGRESS_INCREMENTS / 10) === 0 && $i) {
                $this->getLogger()->info("$i...");
            }
            $contents      = $redis->get($key);
            $ttl           = $redis->ttl($key);
            $contentString = preg_replace("/\s+/", " ", print_r($contents, true));
            $contents      = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (is_int($contents['validUntil']) && $contents['validUntil'] > 0) {
                $this->getLogger()->debug("Applying TTL on session object: $key, $ttl: $contentString");
                $this->countSessionTTLsUpdated++;
                if ($this->perform()) {
                    $redis->expireat($key, $contents['validUntil']);
                }
            } else {
                $this->getLogger()->warning("Invalid 'validUntil' field: $key, $ttl: $contentString");
            }
            $i++;
        }
    }


    /**
     * Print script statistics
     */
    private function printResults(): void
    {
        $this->getLogger()->notice("Max memory used: " . memory_get_peak_usage(true));
        $this->getLogger()->notice("Count sessionStorage deleted: {$this->countSessionStorageDeleted}");
        $this->getLogger()->notice("Count UeberDB set members deleted:   {$this->countUeberDBDeleted}");
        $this->getLogger()->notice("Count Session TTLs updated:   {$this->countSessionTTLsUpdated}");
    }
};

(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Logger::NOTICE]))->runApp();
