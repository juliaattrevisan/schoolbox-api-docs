#!/usr/bin/php
<?php

/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Redis;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Notification\Decorator;

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
        return;
    }

    /**
     * @param array $args
     * @param bool $regexMode
     */
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

        foreach ($redisList as $key) {
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $trigger    = unserialize($uncompress);
            if (is_object($trigger)) {
                $this->interrogateObject($trigger);
                $this->getLogger()->notice(get_class($trigger));
            }
        }
    }


    private function interrogateObject(object $object): object
    {
        $hash = spl_object_hash($object);

        // Don't process the same object more than once
        if (isset($this->objectMap[$hash])) {
            return $this->objectMap[$hash];
        }

        $this->objectMap[$hash] = $object;
        $reflection = new ReflectionObject($object);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $reflectionProperty->setAccessible(true);
            // Maybe this is being accessed before init
            if ($reflectionProperty->isInitialized($object)) {
                $property = $reflectionProperty->getValue($object);
                if ($property instanceof Alaress_Date_NullDate) {
                    // Set this object's property to null
                    $reflectionProperty->setValue($object, null);
                } elseif (is_object($property)) {
                    $property = $this->interrogateObject($property);
                    $reflectionProperty->setValue($object, $property);
                }
            }
        }
        return $object;
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
