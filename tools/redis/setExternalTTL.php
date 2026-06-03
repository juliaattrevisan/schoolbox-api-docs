#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Redis;
use Monolog\Logger;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';


/**
 * Scans redis data for External DB cache and sets their TTL's
 * respecting the current config.
 */
$app = new class extends CliTool\App {
    private const EXTERNAL_DB_KEY_PATTERN = 'External_*';

    /** @inheritDoc */
    public function run(): void
    {
        $redis = Redis::getCacheClientInstance();

        if (!$redis) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }

        $externalDbCacheTimeout = $this->getExternalDbCacheTTL();
        if ($externalDbCacheTimeout === null) {
            $this->getLogger()->info("'external_cache_timeout' is unset. Exiting");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $cursor  = null;

        while ($cursor !== '0') {
            [$cursor, $elements] = $redis->scan($cursor, ['MATCH' => $this->getLikeCacheKey()]);
            foreach ($elements as $cacheKey) {
                $currentTtl = $redis->ttl($cacheKey);
                $this->getLogger()->info("Cache TTL for $cacheKey is: $currentTtl seconds");
                if ($currentTtl > $externalDbCacheTimeout) {
                    $this->getLogger()->info("Set cache TTL for $cacheKey from $currentTtl to $externalDbCacheTimeout seconds");
                    $redis->expire($cacheKey, $externalDbCacheTimeout);
                }
            }
        }
    }

    /**
     * @return null|int
     */
    public function getExternalDbCacheTTL(): ?int
    {
        $value = $this->getConfig()->get('external_cache_timeout');
        return $value === null ? null : (int)$value;
    }

    /**
     * Get the string match like token for searching for redis keys
     *
     * Based off the format of \Alaress\Redis::getRedisKey
     *
     * @return string
     */
    private function getLikeCacheKey(): string
    {
        return sprintf(
            "%s.%s.%s:*",
            Redis::FUNCTION_CALL_CACHE_PREFIX,
            self::EXTERNAL_DB_KEY_PATTERN,
            INSTANCE_ID,
        );
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO]))->runApp();
