#!/usr/bin/php
<?php

/**
 * The Bell Daemon.
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Date\Clock;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\CampusMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TriggerFactory;
use Schoolbox\Realtime\Timetable\TimetableScheduler;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel('Bell');


// CREATE BELL APP
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) use ($kernel): void {
    $kernel->deprecationNotice("SA-19334");

    try {
        /** @var IConfigLoader $conf */
        $conf = $di->get('config');

        /** @var CampusMapper $campusMapper */
        $campusMapper = $di->get('mapperFactory')->get(CampusMapper::class);

        // TODO: for now, this is just hardcoded to Senior for MGGS: the scheduler will accept any Collection of Campuses to schedule
        $campusList = $campusMapper->findByColumn('ext_timetable_code', 'SEN');
        $clock      = new Clock();
        $factory    = new TriggerFactory($clock);
        $strat      = new DefaultDispatchStrategy($flags);

        new TimetableScheduler($loop, $strat, $log, $campusList, $factory, $conf, $clock);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to RabbitMQ: server is offline.');
        $n = Notifactory::create(Notif::TOPIC_NOTIFICATION);
        $n->alert('RabbitMQ is offline');
        throw $e;
    }
    $kernel->deprecationNotice("SA-19334");
});
