#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\Assessment\Rubric\DataModel as RubricDataModel;
use Schoolbox\Filter;
use Schoolbox\Mapper;
use Schoolbox\Resource\LogMapper;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Redis Notif Cleanup',
    [
        Daemon\Kernel::OPTION_ERRORS        => E_ALL,
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['perform'],
    ]
);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {
    $perform = \array_key_exists('perform', $opts);

    if ($perform) {
        $log->warning('Running in perform mode; matched data will be deleted');
    } else {
        $log->notice('Running a dry-run; your data is safe');
    }

    $redis = \Alaress\Redis::getClientInstance();

    /** @var Mapper[] $offendingMappers */
    $offendingMappers = [
        LogMapper::getInstance(),
        Filter\Selection\Mapper::getInstance(),
        RubricDataModel\Indicator\Mapper::getInstance(),
        RubricDataModel\Descriptor\Mapper::getInstance(),
        RubricDataModel\Level\Mapper::getInstance(),
        RubricDataModel\IndicatorMark\Mapper::getInstance(),
        RubricDataModel\Result\Mapper::getInstance(),
        RubricDataModel\IndicatorUsage\Mapper::getInstance(),
    ];

    $modelClasses = [];

    foreach ($offendingMappers as $mapper) {
        $modelClasses[] = str_replace('\\', '\\\\\\\\', $mapper->getModel());
    }

    $regex = sprintf('`(%s)`', implode('|', $modelClasses));

    $keys = $redis->keys(sprintf('msg:%s:e:*', INSTANCE_ID));

    $log->notice('Parsing ' . count($keys) . ' records');

    $deletions = [];

    foreach ($keys as $key) {
        $val = $redis->get($key);
        if (preg_match($regex, $val, $matches)) {
            $len = strlen($val);
            $log->debug(sprintf('[MATCH] %s (%d bytes) matched on %s', $key, $len, $matches[0]));
            $deletions[$key] = $len;
        }
    }

    $log->notice(sprintf(
        'Total reclamation: %d keys accounting for %s',
        count($deletions),
        Alaress_Byte_Format::bytesToString(array_sum($deletions))
    ));

    if ($perform) {
        $redis->del(array_keys($deletions));
        $log->error('Data deletion successful');
    }
});
