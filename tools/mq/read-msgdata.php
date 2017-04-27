#!/usr/bin/php
<?php

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;


if (count($argv) < 2) {
    die('Need one or more hash arguments, got ' . ($argc - 1) . "\n");
}

require_once __DIR__ . '/../../library/Bootstrap.php';

for ($i = 1; $i < $argc; ++$i) {
    if (!preg_match('`^([0-9a-f]{40}):([0-9a-f]{40})$`', $argv[$i], $matches)) {
        echo "Invalid hash: $argv[$i]\n";
        continue;
    }

    $key = sprintf('msg:%s:m:%s', INSTANCE_ID, $matches[0]);

    /** @var Instance $instance */
    $instance = unserialize(gzuncompress(Redis::getClientInstance()->get($key)));

    $output[$matches[1]][$matches[2]] = $instance->jsonSerialize();
}

if (empty($output)) {
    die("No data\n");
}

print_r($output);
exit;