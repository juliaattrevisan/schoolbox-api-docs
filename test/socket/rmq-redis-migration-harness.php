#!/usr/bin/php
<?php
use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Queue\AMQPQueue;
use Alaress\Async\Queue\QueueInterface;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\UserQueue\UserQueue;
use Alaress\Lumberjack\Logger;
use Schoolbox\Notif\AbstractQueueFactory;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\Notif\Queue\DigestQueue;
use Schoolbox\Notif\Queue\ListedQueue;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

/**
 * WARNING: RUN THIS SCRIPT IN **VAGRANT ONLY**. IT WILL PURGE YOUR REDIS AND AMQP!
 *
 * @purpose     Verify that notification ALL persistent data from 14.0.x paradigm is
 *              safely and completely transferred from AMQP to Redis under 14.5.x paradigm
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


$override = 'ialsoliketolivedangerously';

$opts = getopt('v', array($override));

require_once __DIR__ . '/../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
new Schoolbox\Application\Daemon\Kernel('AMQP2REDIS MIGRATION TEST HARNESS');


// yes, I *know* classes in this file is not kosher, but these classes should never be used elsewhere

/**
 * Class AmqpQueueFactory
 * Used specifically for creating a 14.0.x-paradigm set of Rabbit queues
 */
class AmqpQueueFactory extends AbstractQueueFactory {

    /**
     * @param int $userId
     * @throws UnexpectedValueException
     * @return QueueInterface
     */
    public function buildForUser($userId)
    {
        $map = array(
            UserQueue::Q_TOSORT => '*.notify.#',
            UserQueue::Q_LISTED => "*.instant.user.$userId.#",
            UserQueue::Q_UNREAD => "*.instant.user.$userId.#",
            UserQueue::Q_DIGEST => "*.digest.user.$userId.#",
        );

        return new AMQPQueue($this->getAMQP(), "$userId.{$this->kind}", isset($map[$this->kind]) ? $map[$this->kind] : '');
    }
}


class Rmq2RedisHarness
{
    /** @var Logger */
    private $log;

    /** @var int */
    private $now;

    /** @var array */
    private $categories;

    /** @var array */
    private $userlist = array();

    /** @var array */
    private $instants = array();

    /** @var array */
    private $digests  = array();

    /**
     * @param Logger $log
     * @param int    $now
     * @param int    $usercount
     */
    public function __construct($log, $now, $usercount = 9)
    {
        $this->log = $log;
        $this->now = $now;
        $this->stopDaemons();
        $this->linea();
        $this->generateTestdata($usercount);
    }

    /**
     * @return int
     */
    public function verify()
    {
        $this->log->notice('Verifying data safely migrated');
        $redis = \Alaress\Redis::getClientInstance();

        $errors = array();

        foreach ($this->categories as $cat => $users) {

            /**
             * Verify the two messages for this category have arrived in Redis
             * @var Instance[] $msgs
             */
            $msgs = array(
                $this->newInstanceForInstant($cat),
                $this->newInstanceForDigest($cat)
            );

            foreach ($msgs as $instance) {
                $key = sprintf('msg:%s:m:%s:%s', INSTANCE_ID, $instance->getBaseHash(), $instance->getParamHash());

                $exists = $redis->executeCommand($redis->createCommand('EXISTS', array($key)));

                if (!$exists) {
                    $this->log->error("Message '$key' did not come across to Redis");
                    $errors[] = "ASSERT EXISTS: '$key'";
                }
            }

            // iterate users to find out where messages ended up
            foreach (array_keys($users) as $userId) {
                /** @var RedisQueue[] $queues */
                $queues = array(
                    new ListedQueue($redis, $userId),
                    new DigestQueue($redis, $userId)
                );

                for ($i = 0; $i < count($queues); ++$i) {
                    for ($j = 0; $j < count($msgs); ++$j) {

                        $queueName = $queues[$i]->getName();

                        $key = sprintf('msg:%s:m:%s:%s', INSTANCE_ID, $msgs[$j]->getBaseHash(), $msgs[$j]->getParamHash());

                        $this->log->debug(sprintf("Verifying %s %s on %s", $key, $i == $j ? 'EXISTS' : 'DOES NOT EXIST', $queueName));
                        // verify that they exist on the correct queues, and ONLY the correct queues
                        if ($queues[$i]->contains($msgs[$j]) != ($i == $j)) {

                            $this->log->error("Message '$key' should only be on queue $queueName");
                            $errors[] = sprintf('ASSERT%s MEMBER: %s in %s', $i == $j ? '' : ' NOT', $key, $queueName);
                        }
                    }
                }
            }
        }

        if (count($errors)) {
            $this->log->alert('Not all data survived migration', array('errors' => $errors));
            return 1;
        }

        $this->log->warning('All data seems intact');
        return 0;
    }

    /**
     * Stop all Schoolbox daemon processes
     */
    private function stopDaemons() {
        $this->log->info('Stopping Schoolbox daemons');

        $this->log->debug('Stopping scheduled');
        `service schoolbox-scheduled stop 2> /dev/null`;

        $this->log->debug('Stopping rerouted');
        `service schoolbox-rerouted stop 2> /dev/null`;

        $this->log->debug('Stopping digestd');
        `service schoolbox-digestd stop 2> /dev/null`;

        $this->log->debug('Stopping socketd');
        `service schoolbox-socketd stop 2> /dev/null`;
    }

    /**
     * Linea: Destroyer of Worlds
     */
    private function linea()
    {
        $this->log->critical('Destroying all message data in Redis');

        $this->log->debug('Clearing keys matching "msg:*"');
        `redis-cli keys "msg:*" | xargs redis-cli del`;

        $this->log->critical('Destroying all data in AMQP');

        $this->log->debug('Stopping rabbit-server');
        `rabbitmqctl stop_app`;

        $this->log->debug('Clearing mnesia');
        `rabbitmqctl reset`;

        $this->log->debug('Restarting rabbit-server');
        `service rabbitmq-server restart`;
    }

    /**
     * @param int $usercount
     */
    private function generateTestdata($usercount)
    {
        $userlist = array(31, 24, 20, 230, 17, 14, 3, 10, 15);

        $count = min($usercount, count($userlist));

        $this->log->info(sprintf("Generating test data for %d user%s", $count, $count == 1 ? '' : 's'));

        $conn = Connector::getInstance();
        $ctrl = new BunnyControl();

        // setup database dependencies
        $uMap = UserMapper::getInstance();

        // setup new object dependencies
        $qf = new AmqpQueueFactory();
        $qf->setAMQP($conn)->setKind(UserQueue::Q_TOSORT);

        $generator = new Generator();
        $binder    = new UserQueueBinder($conn, $ctrl, $qf, $generator);

        // we're using all the kinds in this instance
        $kinds = array(UserQueue::Q_TOSORT, UserQueue::Q_UNREAD, UserQueue::Q_DIGEST, UserQueue::Q_LISTED);

        // this will list the categories to which our users are subscribed
        $this->categories = array();

        // iterate the user list and generate queues for each
        for ($i = 0; $i < $count; ++$i) {
            $user = $uMap->findById($userlist[$i]);

            $binder->setUser($user)->performBind($kinds);

            $this->log->info(sprintf('Queues added for %s', $user->getUsername()));

            foreach ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_HOMEPAGE) as $access) {
                /** @var UserAccess $access */
                $this->categories[$access->getServiceIdentifier()][$user->getId()] = true;
            }

            $this->userlist[] = $user;
        }

        $config = \Schoolbox\Config\ConfigMapper::getInstance();
        foreach ($this->categories as $cat => $users) {
            $this->log->info("Creating notifications for category $cat");

            // spawn a new notification instance for this category
            $instant = $this->newInstanceForInstant($cat);
            $digest  = $this->newInstanceForDigest($cat);

            foreach(array_keys($users) as $userId) {
                $this->log->debug("Sending category $cat notifications to user $userId");

                // send each instance to the respective lists of each user
                \Schoolbox\Notif\Notifactory::create("instant.user.$userId", $config)->notice("$instant");
                \Schoolbox\Notif\Notifactory::create("digest.user.$userId", $config)->info("$digest");
            }

            $this->instants[$cat][] = $instant;
            $this->digests[$cat][]  = $digest;
        }

        $this->log->warning("Data generation complete");
    }

    /**
     * @param int $catId
     * @return Instance
     */
    private function newInstanceForDigest($catId)
    {
        return $this->newBob("Digest", $catId)->setSeverity(\Monolog\Logger::INFO)->build();
    }

    /**
     * @param int $catId
     * @return Instance
     */
    private function newInstanceForInstant($catId)
    {
        return $this->newBob("Instant", $catId)->setSeverity(\Monolog\Logger::NOTICE)->build();
    }

    /**
     * @param string $type
     * @param int $id
     * @return Builder
     */
    private function newBob($type, $id)
    {
        $sha = sha1(strtoupper("$type-$id"));
        $minisha = substr($sha, 0, 10);

        $bob = new Builder();
        $bob->setNamespace('testdata')
            ->addTopic('test.data')
            ->setHref("/messages/$sha")
            ->setTitle("$type :id ($minisha)")
            ->setParam('id', new Param($id, "/homepage/$id"))
            ->setIcon('/images/core/apple-touch-icon-precomposed.png')
            ->setTimestamp($this->now - (1000 * $id));

        return $bob;
    }

}


// aaaand, here's the rest of the script execution

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

$log = new Logger(STDOUT, null, $limits[$threshold]);

if (INSTANCE_ID != 'vagrantx' && !isset($opts[$override])) {
    $log->emergency("!! This script is for testing inside a Vagrant only (override with the --$override flag) !!\n");
    exit;
}

if (posix_getuid() !== 0) {
    $log->emergency("Make me a sandwich\n");
    exit;
}


$log->alert('AMQP2REDIS MIGRATION TEST HARNESS');

// set precondition
new AmqpQueueFactory();
$harness = new Rmq2RedisHarness($log, time());

// perform migration
$log->notice('Performing migration');
require_once __DIR__ . '/../../updates/14.5.rc4/notif-migration/migrate-to-redis.php';
$log->warning('Migration complete');

// verify results
exit($harness->verify());
