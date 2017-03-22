#!/usr/bin/php
<?php

use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Digest\DigestScheduler;
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Realtime\MessageList\Factory;


/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest', array(
	'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'flags'   => $flags
));



// CREATE DIGEST APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $dispatch = new DefaultDispatchStrategy($opts['flags']);
    $notifier = new MailNotifier(new Factory(INSTANCE_ID, $redis));

    new DigestScheduler($loop, $dispatch, $notifier, $log, $conf);
});
