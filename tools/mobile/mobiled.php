#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use Alaress\Date\Clock;
use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use Alaress\Sentry\Tracing\Tracer;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceManager;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Mobile\Broker;
use Schoolbox\Realtime\Mobile\Scheduler;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Mobile App Delivery Daemon',
    [
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'h',
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => [
            'help',
            'force-production',
            'retry-max-attempts:',
            'retry-base-timeout:',
        ]
    ],
);

// Create the mobile app delivery daemon.
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {
    /** @var Instance $instance */
    $instance   = $di->get('instance');
    $instanceId = $instance->getId();

    if (isset($opts['help']) || isset($opts['h'])) {
        $queue = Broker::QUEUE;
        echo <<<HELP
NAME:
    Mobile App Delivery Daemon

SYNOPSIS:
    ./mobiled.php [OPTION]...

DESCRIPTION:
    Daemon for listening to requests to forward notifications to a mobile app or personal web app. This may use one of
    the following delivery mechanisms:
    * Apple Push Notification Service
    * Digistorm API
    * Firebase
    * Google Cloud Messaging

    This daemon listens on an AMQP queue named `{$queue}`, whose data is stored in Redis:
    * `msg:{$instanceId}:s:*`


OPTIONS:
  -h, --help                    Display this help message
  --retry-max-attempts          If the service manager detects that a delivery mechanism has been rate limited:
                                retry the delivery this many times. (Defaults to 15 retries.)
  --retry-base-timeout          If the service manager detects that a delivery mechanism has been rate limited, this
                                daemon will exponentially backoff between retries, for a base timeout of this many ms.
                                (Defaults to 2500ms.)
  --force-production            If set, force usage of the production mobile service manager, even on staging instances.
                                This ensures that messages are actually delivered to subscribed mobile devices:
                                otherwise, this daemon will only log on staging instances.


HELP;
        return;
    }

    /** @var Notif $notif */
    $notif = $di->get('notif');

    $clock = new Clock();

    /** @var ClientInterface $redis */
    $redis = $di->get('redis.storage');

    /** @var IConfigLoader $config */
    $config = $di->get('config');

    /** @var IClient $stomp */
    $stomp = $di->get('stomp');

    try {
        /** @var \Schoolbox\Notif\MQ\Connector $amqp */
        $amqp = $di->get('amqp');

        // we'll be using the mobile broker for receiving...
        $broker = new Broker($instanceId, $amqp, $redis, $stomp, Tracer::getDefaultTrace());

        $forceProduction = isset($opts['force-production']);
        if ($forceProduction) {
            $log->notice('Forcing delivery of messages to subscribed mobile devices');
        }

        // Configure jitter
        $retryMaxAttempts = filter_var(
            $opts['retry-max-attempts'] ?? null,
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE,
        );
        if (isset($retryMaxAttempts)) {
            $log->notice(sprintf('Retry max attempts: %d', $retryMaxAttempts));
        } else {
            $retryMaxAttempts = 15;
            $log->notice(sprintf('Retry max attempts: %d (default)', $retryMaxAttempts));
        }
        $retryBaseTimeout = filter_var(
            $opts['retry-base-timeout'] ?? null,
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE,
        );
        if (isset($retryBaseTimeout)) {
            $log->notice(sprintf('Retry base timeout: %dms', $retryBaseTimeout));
        } else {
            $retryBaseTimeout = 2500;
            $log->notice(sprintf('Retry max attempts: %dms (default)', $retryBaseTimeout));
        }
        $jitter = new Jitter(new FloatGenerator(), $retryMaxAttempts, $retryBaseTimeout);

        // Create mobile service manager
        $serviceManager = new ServiceManager(
            $log,
            $di->get('mapperFactory')->get(RegistryMapper::class),
            $instance,
            $config->get('title'),
            $di->get('filesystem.storageProxy'),
            new UnreadRetriever($redis),
            $forceProduction
                ? ServiceManager::SERVICE_TYPE_PRODUCTION
                : ServiceManager::SERVICE_TYPE_BASED_ON_INSTANCE_TYPE,
            $config,
        );

        // Dispatch to mobile queue
        $strat     = new DefaultDispatchStrategy($flags);
        $scheduler = new Scheduler($loop, $strat, $log, $clock, $serviceManager, $jitter);

        // This daemon does not need to restore its messages from the Redis
        // cache, like scheduled does.
        // This is because this daemon does not acknowledge messages
        // (thus removing them from the queue) and then defer processing of them
        // using a React loop (thus losing that message if the daemon fails).

        // due to the async nature of STOMP:
        // only register consumer once the connection is actually established
        $stomp->connect()->then(function () use ($broker, $scheduler, $log) {
            $log->info('Connected to STOMP', ['char' => '+']);
            $broker->registerConsumer($scheduler);
            $log->debug('Consuming amqp://' . Broker::QUEUE);
        });
    } catch (ConnectionException $e) {
        $log->alert('AMQP server is offline or STOMP misconfigured: ' . $e->getMessage(), ['exception' => $e]);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to AMQP server: server is offline.');
        $notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert('RabbitMQ is offline');

        throw $e;
    }
});
