#!/usr/bin/php
<?php
/**
 * Safe script for determining whether all external dependencies are up and running
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Lumberjack\Logger;

$errors = array();
$opts = getopt('v', array('mail'));
$mail = isset($opts['mail']);

error_reporting(E_ALL);

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');

$d = new Logger(STDOUT, STDERR, $limits[$threshold]);
$d->info("Testing external dependencies are online...");

try {
    new \Schoolbox\Application\Daemon\Kernel('System Check');

    $threshold = 0;
    if(isset($opts['v'])) {
        $threshold = 1;
        if(is_array($opts['v'])) {
            $threshold = min(3, count($opts['v']));
        }
    }

    $limits = array(
        0 => 500, // nil verbosity: exceptional behaviour only
        1 => 250, // min verbosity: significant events
        2 => 200, // mod verbosity: most things
        3 => 100  // max verbosity: EV-ER-Y-THING...
    );

    // test database is online
    if (!Database::getInstance()->isConnected()) {
        throw new PDOException("Database is not connected");
    }
    $d->notice('MySQL is online', array('char' => '+'));

    try {
        // test redis is online
        $r = \Alaress\Redis::getClientInstance();
        $r->executeCommand($r->createCommand('get', array('test')));
        $d->notice('Redis is online', array('char' => '+'));

    } catch (\Predis\PredisException $e) {
        $d->alert('Redis is offline');
        $errors[] = 'Redis';
    }

    try {
        // test amqp is online
        $amqp = \Schoolbox\Notif\MQ\Connector::getInstance();
        $amqp->publish('Testing AMQP Connection', 'daemon.test');
        $d->notice('RabbitMQ is online', array('char' => '+'));

    } catch (Exception $e) {
        $d->alert('RabbitMQ is offline');
        $errors[] = 'RabbitMQ';
    }

} catch (PDOException $e) {
    $d->alert('MySQL is offline: cannot read Redis or RabbitMQ config');
    $errors = array('MySQL', 'Redis', 'RabbitMQ');
    $mail = false;
}

if (count($errors)) {
    if ($mail) {
        $n = new \Schoolbox\Notif\Notif(\Schoolbox\Notif\Notif::TOPIC_SYSTEM);
        $n->alert("The following services are offline or uncontactable: ".implode(', ', $errors));
    }
    exit(1);
}

exit(0);