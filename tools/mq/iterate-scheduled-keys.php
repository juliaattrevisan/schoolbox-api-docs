#!/usr/bin/php
<?php

/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Date\Util\NullDateConverter;
use Alaress\Redis;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    private array $objectMap = [];

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $this->checkRedis();
    }


    private function checkRedis(): void
    {
        $redis = Redis::getStorageClientInstance();

        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->keys('msg:' . INSTANCE_ID . ':s:*');

        $this->getLogger()->info("Checking redis data...");

        $converter = new NullDateConverter();

        foreach ($redisList as $key) {
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $trigger    = unserialize($uncompress);
            if (is_object($trigger)) {
                $converter->convert($trigger);
                $this->getLogger()->notice(get_class($trigger));
            }
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
