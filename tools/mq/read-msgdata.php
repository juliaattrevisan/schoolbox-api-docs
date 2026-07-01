#!/usr/bin/env php
<?php

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\BaseApp
{
    protected function getName(): string
    {
        return 'read-msgdata';
    }

    protected function getDescription(): string
    {
        return 'Looks up and prints raw notification message data stored in Redis for the given hash arguments.';
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function run(): void
    {
        global $argv, $argc;

        if (count($argv) < 2) {
            $this->getLogger()->error('Need one or more hash arguments, got ' . ($argc - 1));
            exit(1);
        }

        for ($i = 1; $i < $argc; ++$i) {
            if (!preg_match('`^([0-9a-f]{40}):([0-9a-f]{40})$`', $argv[$i], $matches)) {
                $this->getLogger()->warning("Invalid hash: {$argv[$i]}");
                continue;
            }

            $key = sprintf('msg:%s:m:%s', INSTANCE_ID, $matches[0]);

            /** @var Instance $instance */
            $instance = unserialize(gzuncompress(Redis::getStorageClientInstance()->get($key)));

            $output[$matches[1]][$matches[2]] = $instance->jsonSerialize();
        }

        if (empty($output)) {
            $this->getLogger()->info("No data");
        } else {
            $this->getLogger()->info("\n" . print_r($output, true));
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
