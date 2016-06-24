#!/usr/bin/php
<?php

use Alaress\Redis;
use Alaress\Routing\Router;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Realtime\Routing\SubscriptionManager;
use Schoolbox\Realtime\Routing\SubscriptionRouter;
use Schoolbox\UserMapper;

/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_values($fields),
    'flags'   => $flags,
));


// CREATE ROUTING APP
$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container  $di
     * @var LoopInterface $loop
     * @var Logger        $log
     * @var array         $opts
     */
    $di->get('stomp')->connect()->then(function ($client) use ($log) {

        $sub = new SubscriptionManager(new Router('.'), $log);
        
        // skipping this step is not advised ;)
        $sub->loadSubscriptions(new Generator(/* just use defaults... */), UserMapper::getInstance());

        $router = new SubscriptionRouter($sub, $client, $log);

        // open thine ears
        $router->listen(INSTANCE_ID);
    });
});
