#!/usr/bin/php
<?php
/**
 *
 * This script is solely responsible for calculating the set of bindings FOR A SPECIFIED INSTANCE.
 * It will:
 *  - calculate ALL bindings for the instance
 *  - cache the set, replacing any previously cached set
 *  - broadcast a SIGHUP (which advises `routed` instances to reload from cache)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\MapperFactory;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

$fields = [ 'userId:' ];

$opts = require __DIR__ . '/../../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Binding Calculator', [
    'verbose'  => isset($opts['v']) ? $opts['v'] : null,
    'fields'   => array_keys($opts),
    'instance' => isset($opts['instance']) ? $opts['instance'] : null,
    'userId'   => isset($opts['userId']) ? $opts['userId'] : null
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    // If no instance ID is provided, fall back to default if it exists
    if(!isset($opts['instance'])) {
        $opts['instance'] = INSTANCE_ID;
    }

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    /** @var MapperFactory $mf */
    $mf = $di->get('mapperFactory');

    /** @var UserMapper $userMapper */
    $userMapper = $mf->get(UserMapper::class);

    $generator = new Generator(
        $di->get('externalUser'),
        $mf->get(UserMapper::class),
        $mf->get(RoleMapper::class),
        $mf->get(UserAccessMapper::class),
        $mf->get(Schoolbox\Resource\Folder\Mapper::class)
    );

    $loaderFactory = (new Subscription\Loader\Factory($redis, $log))
        ->setUserMapper($userMapper)
        ->setGenerator($generator);

    $cache = new WriteCache($redis, $log, $loaderFactory);

    if ($opts['userId']) {
        $cache->cacheUser($opts['instance'], $opts['userId']);

    } else {
        $cache->cacheAllUsers($opts['instance']);
    }

});
