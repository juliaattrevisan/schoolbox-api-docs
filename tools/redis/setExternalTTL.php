#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

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
        $redis = \Alaress\Redis::getClientInstance();

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
            [$cursor, $elements] = $redis->scan($cursor, ['MATCH' => self::EXTERNAL_DB_KEY_PATTERN]);
            foreach ($elements as $cacheKey) {
                if ($redis->ttl($cacheKey) > $externalDbCacheTimeout) {
                    $this->getLogger()->info('Set cache TTL for ' . $cacheKey . ' to ' . $externalDbCacheTimeout . ' seconds');
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
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::NOTICE]))->runApp();
