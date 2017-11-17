<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Filter;

$fields = [ 'perform' ];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Redis Notif Cleanup', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {
    $perform = in_array('perform', $opts['fields']);

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
        Rubric\CriterionMapper::getInstance(),
        Rubric\DescriptorMapper::getInstance(),
        Rubric\LevelMapper::getInstance(),
        Rubric\MarkMapper::getInstance(),
        Rubric\ResultMapper::getInstance(),
        Rubric\UsageMapper::getInstance(),
    ];

    $modelClasses = [];

    foreach ($offendingMappers as $mapper) {
        $modelClasses[] = str_replace('\\','\\\\\\\\', $mapper->getModel());
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
        Alaress_Byte_Format::bytesToString(array_sum($deletions)))
    );

    if ($perform) {
        $redis->del(array_keys($deletions));
        $log->error('Data deletion successful');
    }
});