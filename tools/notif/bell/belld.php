#!/usr/bin/php
<?php

use Alaress\Date\Clock;
use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
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
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = [ 'porcelain', 'mem', 'truemem', 'pid' ];
$opts   = getopt('v', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Bell', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'flags'   => $flags
]);



// CREATE BELL APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    try {
        /** @var IConfigLoader $conf */
        $conf = $di->get('config');

        /** @var Client $stomp */
        $stomp = $di->get('stomp');

        /** @var CampusMapper $campusMapper */
        $campusMapper = $di->get('mapperFactory')->get(CampusMapper::class);

        // TODO: for now, this is just hardcoded to Senior for MGGS: the scheduler will accept any Collection of Campuses to schedule
        $campusList = $campusMapper->findByColumn('ext_timetable_code', 'SEN');
        $clock      = new Clock();
        $factory    = new TriggerFactory($clock);
        $strat      = new DefaultDispatchStrategy($opts['flags']);

        new TimetableScheduler($loop, $strat, $stomp, $log, $campusList, $factory, $conf, $clock);

    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to RabbitMQ: server is offline.');
        $n = Notifactory::create(Notif::TOPIC_NOTIFICATION);
        $n->alert('RabbitMQ is offline');
        throw $e;
    }
});
