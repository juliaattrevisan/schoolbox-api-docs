#!/usr/bin/php
<?php

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;

require_once __DIR__ . '/../../library/Bootstrap.php';

$redis = Redis::getClientInstance();
$keys = $redis->keys('msg*');

foreach ($keys as $idx => $key) {
    echo "=====================================================================\n";
    echo "[$idx] $key \n";

    /** @var Instance $instance */
    try {
        $keytype = $redis->type($key);
        $rawRedisData = $redis->get($key);
        $instance = unserialize(gzuncompress($rawRedisData));
    } catch (Exception $e) {
        echo "Type of {$key} is {$keytype}\n";
        continue;
    }

    if ($instance instanceof \Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger) {
        echo "CompressedTrigger \n";
        $output = $instance->extractTrigger();
        echo date('Y-m-d H:i:s', $output->getTimestamp()) . "\n";
        echo $output->getLabel() . ' ' . $output->getEvent()->getAction() . ' ' . $output->getEvent()->getObject()->getId() . "\n";

    } else if ($instance instanceof \Alaress\Scheduler\Trigger\CancelTrigger) {
        echo "CancelTrigger \n";
        $output = $instance->getTimestamp();
        echo date('Y-m-d H:i:s', $output['time']) . "\n";
        echo $instance->getLabel() . "\n";
    } else {
        $output = $instance->jsonSerialize();
        echo date('Y-m-d H:i:s', $output['time']) . "\n";
        echo $output['title']['text'] . "\n";
    }
}
exit;
