#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Routing;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel('Route Worker');

$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log) {

    // extract things from DI and build the worker

    /** @var ClientInterface $client */
    $client = $di->get('redis');

    /** @var IConfigLoader $config */
    $config = $di->get('config');

    /** @var Instance $instance */
    $instance = $di->get('instance');

    /** @var RegistryMapper $registryMapper */
    $registryMapper = $di->get('mapperFactory')->get(RegistryMapper::class);

    /** @var UserMapper $userMapper */
    $userMapper = $di->get('mapperFactory')->get(UserMapper::class);

    /** @var RoutePermissionMapper $routePermMapper */
    $routePermMapper = $di->get('mapperFactory')->get(RoutePermissionMapper::class);

    (new Routing\WorkerFactory($config, $log, $client, $registryMapper, $userMapper, $routePermMapper, $instance))
        ->build($loop, INSTANCE_ID);
});

// yup. that's all you need to do.
