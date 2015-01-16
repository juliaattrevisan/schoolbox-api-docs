#!/usr/bin/hhvm
<?php

use Alaress\Async\UserQueue\UserQueue;
use Alaress\Async\Notification\Cloud;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Aura\Di;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use React\Stomp\Factory;
use Schoolbox\MobileApp;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\SessionValidator;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('vp::c::', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Socket', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'port'    => empty($opts['p']) ? 9081 : $opts['p'],
    'conf'    => empty($opts['c']) ? 'socketd.conf' : $opts['c'],
    'fields'  => array_values($fields),
    'flags'   => $flags
));

// CREATE WEBSOCKET APP
$kernel->run(
       function ($di, $loop, $log, $opts) {
           /**
            * @var Di\Container             $di
            * @var LoopInterface            $loop
            * @var \Psr\Log\LoggerInterface $log
            * @var array                    $opts
            */
           $redis = Alaress\Redis::getClientInstance();
           $redis->connect();

           $stratConfig = array();
           if (file_exists($opts['conf']) && is_readable($opts['conf'])) {
               $fp = fopen($opts['conf'], 'r');
               while ($line = fgets($fp)) {
                   $xpl = explode('=', $line);
                   if (count($xpl) == 2) {
                       $stratConfig[$xpl[0]] = $xpl[1];
                   }
               }
               fclose($fp);
           } else {
               $log->warning("Couldn't read config: using default strat");
           }

           $stratBuilder = new ConfiguredDispatchStrategyBuilder($stratConfig);
           $poolStrat    = $stratBuilder->build($opts['flags']);

           $delim = in_array('porcelain', $opts['fields']) ? ' ' : "\n    ";

           $log->info(implode($delim, array("Using strategy:") + $stratBuilder->summarise()), array('char' => '+'));

           $conf             = $di->get('config');
           $sessionValidator = new SessionValidator($redis, new PhpHandler(), $conf->get('session_length'), Database::getInstance());

           if ($conf->get('push_notifications_enabled')) {
               $services = array(
                   new Cloud\Google\GCM($conf->get('push_gcm_api_key')),
                   new Cloud\APNS()
               );
           } else {
               $services = array();
           }

           $cloudManager = new MobileApp\ServiceManager($services);

           $stompFactory = new Factory($loop);

           // we have but one STOMP client (ie. socket connection) to rabbit for all our subscriptions
           $client = $stompFactory->createClient(array(
               'host'     => $conf->get('amqp_host'),
               'port'     => $conf->get('amqp_stomp_port'),
               'vhost'    => $conf->get('amqp_vhost'),
               'login'    => $conf->get('amqp_user'),
               'passcode' => $conf->get('amqp_pass'),
           ));

           $client->connect(2)
                  ->then(
                  function ($client) use ($loop, $sessionValidator, $cloudManager, $log, $poolStrat, $opts, $redis) {

                      $mqConn = Connector::getInstance();

                      $queueFactory = new QueueFactory();
                      $queueFactory->setAMQP($mqConn);
                      $queueFactory->setKind(UserQueue::Q_UNREAD);

                      $pipeManager  = new PipeManager($loop, $sessionValidator, $client, $queueFactory, $cloudManager, $log, $poolStrat);

                      $wss = new Ratchet\WebSocket\WsServer($pipeManager);
                      $wss->disableVersion(0);

                      $https = new Ratchet\Http\HttpServer($wss);

                      try {
                          $webSock = new React\Socket\Server($loop);
                          $webSock->listen($opts['port'], '0.0.0.0');

                          new Ratchet\Server\IoServer($https, $webSock, $loop);

                          $log->info("Listening for incoming websockets on port {$opts['port']}...", array('char' => '+'));
                      } catch(ConnectionException $e) {
                          $log->alert("Unable to launch: " . $e->getMessage(), array($e));
                          throw $e;
                      }

                  }
           );
       }
);


