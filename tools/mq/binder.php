#!/usr/bin/php
<?php
use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\UserQueue\UserQueue;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\MQ\Test\MockAMQPConnector;
use Schoolbox\Notif\MQ\Test\MockBunnyControl;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Realtime\Rerouter\UserSubscriptionList;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (posix_getuid() !== 0) {
    die("[!] Try sudo.\n");
}


$fields = array('porcelain', 'mem', 'truemem', 'pid', 'dry-run');

$opts = getopt('v', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Binder', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'flags'   => $flags,
    'dry-run' => isset($opts['dry-run'])
));

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */
    if ($opts['verbose']) {
        echo <<<EOL

 ||
 || Schoolbox MessageQueue Binder
 ||


EOL;
    }

    $conf = ConfigMapper::getInstance();

    // setup dry-run dependencies
    $conn = $opts['dry-run'] ? new MockAMQPConnector() : Connector::getInstance();
    $ctrl = $opts['dry-run'] ? new MockBunnyControl() : new BunnyControl([
        'host'     => $conf->get('amqp_host'),
        'port'     => $conf->get('amqp_port'),
        'username' => $conf->get('amqp_user'),
        'password' => $conf->get('amqp_pass'),
        'vhost'    => $conf->get('amqp_vhost')
    ]);

    // setup database dependencies
    $uMap = UserMapper::getInstance();

    // setup new object dependencies
    $qf = new QueueFactory();
    $qf->setAMQP($conn)
       ->setKind(UserQueue::Q_TOSORT);

    $generator = new Generator();
    $binder    = new UserQueueBinder($conn, $ctrl, $qf, $generator);
    $subList   = new UserSubscriptionList($qf, $log);

    // import data from database
    $log->debug("Importing user data");
    $subList->importFromSource($uMap);
    $log->warning("Import complete");

    // final preflight details
    $userCount = count($subList->getAllQueueNames());

    if ($userCount == 0) {
        $log->error("No users found, no work to do. Exiting.");
        return;
    }

    $countLen  = strlen("$userCount");
    $pattern   = "BIND [%{$countLen}d/%{$countLen}d]  %4s/%4s: %s";

    $log->debug("Preparing to bind $userCount users");

    // and go.
    foreach ($subList->getAllQueueNames() as $i => $queue) {
        /** @var User $user */
        $user = $subList->getQueueData($queue);

        $kinds = array(UserQueue::Q_TOSORT, UserQueue::Q_UNREAD);

        list($removed, $added) = $binder
            ->setUser($user)
            ->performBind($kinds);

        // advise progress
        $log->notice(sprintf($pattern, $i, $userCount, "-$removed", "+$added", $user->getUsername()));
    }

    $log->debug("Finished binding, notifying Rerouter");

    $cmdr = new \Schoolbox\Realtime\Rerouter\RerouterCommander($uMap);
    $cmdr->adviseReload();

    $log->notice("Done.");

});


