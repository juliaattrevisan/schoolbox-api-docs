#!/usr/bin/php
<?php

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    /** @inheritDoc */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

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

        $this->deprecationNotice("SA-19334");
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
