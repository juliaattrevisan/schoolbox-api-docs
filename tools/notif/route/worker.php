#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceManager;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList\Mobile;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel('Route Worker');

$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log) {
    // extract things from DI and build the worker

    /** @var ClientInterface $client */
    $client = $di->get('redis.storage');

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

    /** @var FilesystemProxy $filesystemProxy */
    $filesystemProxy = $di->get('filesystem.storageProxy');

    /** @var \Schoolbox\Mail\EmailFactory $emailFactory */
    $emailFactory = $di->get('mail.emailFactory');

    // Build a mobile queue factory
    $mobileQueueFactory = $config->get(Schoolbox\Config\ConfigMapper::NAME_MOBILE_DAEMON_ENABLED)
        ? new Mobile\ForwardToBrokeredQueueFactory(
            new Schoolbox\Realtime\Mobile\Broker(
                INSTANCE_ID,
                $di->get('amqp'),
                $client,
                $di->get('stomp'),
            ),
            INSTANCE_ID,
        )
        : new Mobile\DirectSendFactory(
            new ServiceManager(
                $log,
                $registryMapper,
                $instance,
                $config->get('title'),
                $filesystemProxy,
                new UnreadRetriever($client),
                ServiceManager::SERVICE_TYPE_BASED_ON_INSTANCE_TYPE,
                $config,
            ),
        );

    (new Routing\WorkerFactory(
        $config,
        $log,
        $client,
        $userMapper,
        $routePermMapper,
        $emailFactory,
        new Jitter(new FloatGenerator(), maxAttempts: 15, baseTimeout: 2500),
        $mobileQueueFactory,
    ))->build($loop, INSTANCE_ID);
});

// yup. that's all you need to do.
