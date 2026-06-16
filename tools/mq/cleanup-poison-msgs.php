#!/usr/bin/env php
<?php

/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Redis;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\BaseApp {
    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    protected function getName(): string
    {
        return 'cleanup-poison-msgs';
    }

    protected function getDescription(): string
    {
        return <<<DESC
            This script is used to remove poisoned messages from the system PERMANENTLY. It will search the entire notification
            message list and delete any messages which are not able to be converted to notifications.

            Please ensure you do a dry run before you run the script as this is unrecoverable.
            DESC;
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $redis = Redis::getStorageClientInstance();
        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->keys('msg:' . INSTANCE_ID . ':m:*');

        $this->getLogger()->info("Checking redis data...");
        $acks = 0;

        foreach ($redisList as $key) {
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $msg        = unserialize($uncompress, ['allowed_classes' => Instance::ALLOWED_UNSERIALIZE_CLASSES]);
            try {
                new Notification([ $msg ]);
            } catch (Error $e) {
                ++$acks;
                $this->getLogger()->warning(
                    sprintf(
                        'Could not decode message %s: (message: %s)',
                        $key,
                        $e->getMessage(),
                    ),
                );
                $this->getLogger()->info('Cleaning up poison message');
                if ($this->perform()) {
                    $redis->del($key);
                }
            }
        }

        if ($acks === 0) {
            $this->getLogger()->info("No poisoned messages found!");
        } elseif ($this->perform()) {
            $this->getLogger()->info("$acks poisoned messages found and removed");
        } else {
            $this->getLogger()->info("$acks poisoned messages found: run with -p to delete");
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
