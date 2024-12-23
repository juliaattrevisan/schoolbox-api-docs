#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
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
 * Iterates over the calendar vcal keys and removes them from the system.
 * These keys are temporary, so they can be disposed of safely
 */
$app = new class extends CliTool\App {

    private const PROGRESS_INCREMENTS = 10000;

    private int $countVcalCacheDeleted = 0;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null; // Doesn't matter.
    }


    /** @inheritDoc */
    public function run(): void
    {
        $redis = $this->getRedisService();
        $this->deleteVcalCache($redis);
        $this->printResults();
    }

    /**
     * @return ClientInterface
     */
    private function getRedisService(): ClientInterface
    {
        $redis = $this->getDi()->get('redis.cache');
        if (!$redis instanceof ClientInterface) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        return $redis;
    }


    /**
     * Deletes vcal cached objects
     *
     * @param ClientInterface $redis
     */
    private function deleteVcalCache(ClientInterface $redis): void
    {
        $this->getLogger()->notice('Starting cleanup of vcal cache');
        $search = new Keyspace($redis, 'vcal.*');
        foreach ($search as $key) {
            // Show some progress every N keys...
            if ($this->countVcalCacheDeleted % self::PROGRESS_INCREMENTS === 0 && $this->countVcalCacheDeleted) {
                $this->getLogger()->info("$this->countVcalCacheDeleted...");
            }
            $this->getLogger()->debug("Removing vcal object: $key");
            $this->countVcalCacheDeleted++;
            if ($this->perform()) {
                $redis->del($key);
            }
        }
    }

    /**
     * Print script statistics
     */
    private function printResults(): void
    {
        $this->getLogger()->notice("Max memory used: " . memory_get_peak_usage(true));
        $this->getLogger()->notice("Count vcal objects deleted: {$this->countVcalCacheDeleted}");
    }
};

(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Logger::NOTICE]))->runApp();
