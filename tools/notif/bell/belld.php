#!/usr/bin/php
<?php

use Alaress\Date\Clock;
use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\CampusMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TriggerFactory;
use Schoolbox\Realtime\Timetable\TimetableScheduler;

/**
 * The Bell Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel('Bell');


// CREATE BELL APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {

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
});
