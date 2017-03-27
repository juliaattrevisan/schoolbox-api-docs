#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Routing;
use Schoolbox\UserMapper;

/** @var array $fields */
$fields = [ 'porcelain', 'mem', 'truemem', 'pid' ];

$opts = getopt('v', $fields, $_);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Route Worker', [
    'errors' => E_ALL,
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),

]);

$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log) {

    // extract things from DI and build the worker

    /** @var ClientInterface $client */
    $client = $di->get('redis');

    /** @var IConfigLoader $config */
    $config = $di->get('config');

    /** @var RegistryMapper $registryMapper */
    $registryMapper = $di->get('mapperFactory')->get(RegistryMapper::class);

    /** @var UserMapper $userMapper */
    $userMapper = $di->get('mapperFactory')->get(UserMapper::class);

    /** @var RoutePermissionMapper $routePermMapper */
    $routePermMapper = $di->get('mapperFactory')->get(RoutePermissionMapper::class);

    (new Routing\WorkerFactory($config, $log, $client, $registryMapper, $userMapper, $routePermMapper))
        ->build($loop, INSTANCE_ID);

});

// yup. that's all you need to do.
