#!/usr/bin/php
<?php

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
new \Schoolbox\Application\Daemon\Kernel(__FILE__, $opts);

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
        if ($keytype->getPayload() === 'set') {
            $setcount = \count($redis->smembers($key));
            echo "{$setcount} messages exist.\n";
        } else {
            echo "Type of {$key} is {$keytype}\n";
        }
        continue;
    }

    if ($instance instanceof \Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger) {
        echo "CompressedTrigger \n";
        $output = $instance->extractTrigger();
        echo date('Y-m-d H:i:s', $output->getTimestamp()) . "\n";
        $classname = get_class($output->getEvent()->getObject());
        $object = $output->getEvent()->getObject();
        $mapper = $instance->getModuleNotifier()->getMapper();
        if ($mapper->getIdentifier()) {
            $getIdMethod = $mapper->getGetIdentifierMethodName();
            $withId      = "with ID {$object->$getIdMethod()}";
        } else {
            $withId = '(which does not have an identifier)';
        }
        echo "Notify {$output->getLabel()}. Object {$classname} {$withId} {$output->getEvent()->getAction()} \n";
    } else if ($instance instanceof \Alaress\Scheduler\Trigger\CancelTrigger) {
        echo "CancelTrigger \n";
        $output = $instance->getTimestamp();
        echo date('Y-m-d H:i:s', $output['time']) . "\n";
        echo $instance->getLabel() . "\n";
    } else {
        $output = $instance->jsonSerialize();
        echo date('Y-m-d H:i:s', $output['time']) . "\n";
        foreach ($output['topics'] as $topic) {
            echo $topic . "\n";
        }
        $message = $output['title']['text'];
        foreach ($output['title']['params'] as $paramName => $paramData) {
            /** @var \Alaress\Async\Notification\Parameter\ParameterInterface $paramData */
            $message = str_replace(":{$paramName}", "[{$paramData->getText()}]", $message);
        }
        echo $message . "\n";
    }
}

echo "\n";
echo "=====================================================================\n";
echo "Cached messages\n";
$rawRedisData = $redis->smembers('schedule:vagrantx:index');
foreach ($rawRedisData as $rawmsg) {
    echo $rawmsg . "\n";
}

exit;
