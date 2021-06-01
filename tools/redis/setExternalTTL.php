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

    private const MAX_TTL_VALUE = 3600;

    /** @inheritDoc */
    public function run(): void
    {
        $redis = \Alaress\Redis::getClientInstance();

        if (!$redis) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }

        $externalKeys = $redis->keys(self::EXTERNAL_DB_KEY_PATTERN);

        if (!empty($externalKeys)) {
            $expiry = $this->getExternalDbCacheTTL();

            foreach ($externalKeys as $key) {
                $this->getLogger()->info('Set cache TTL for ' . $key . ' to ' . $expiry . ' seconds');
                $redis->expire($key, $expiry);
            }
        } else {
            $this->getLogger()->info('Unable to find keys with pattern of ' . self::EXTERNAL_DB_KEY_PATTERN . ' exiting...');
        }
    }

    /**
     * @return int
     */
    public function getExternalDbCacheTTL(): int
    {
        $cacheTimeout = (int) $this->getConfig()->get('external_cache_timeout');

        return $cacheTimeout <= self::MAX_TTL_VALUE ? $cacheTimeout : self::MAX_TTL_VALUE;
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::NOTICE]))->runApp();
