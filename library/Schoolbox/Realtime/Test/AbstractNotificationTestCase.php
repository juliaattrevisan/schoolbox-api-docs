<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Test;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Async\Test\AbstractTestCase;
use Alaress\Redis;
use OutOfBoundsException;
use Predis\ClientInterface;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\User;
use Schoolbox\UserMapper;

abstract class AbstractNotificationTestCase extends AbstractTestCase
{
    /* COMMON USERS*/

    // the Rhodes family
    const DAVID_RHODES          = 230;
    const CALEB_RHODES          = 245;
    const JO_RHODES             = 75;
    const MADDISON_RHODES       = 206;
    const PETER_RHODES          = 74;
    const RACHEL_RHODES         = 229;

    // the Saxon family
    const ANGELO_SAXON          = 226;
    const ANITA_SAXON           = 17;
    const LOUISE_SAXON          = 20;
    const SOPHIA_SAXON_TEACHER  = 160;
    const SOPHIA_SAXON_PARENT   = 244;

    // various teachers
    const GLENDA_SMITH          = 157;
    const JACKIE_BENSON         = 247;
    const ZANE_SMITH            = 151;
    const GARY_GEORGE           = 153;
    const ANNE_SMITH            = 159;
    const RYAN_VOLLMER          = 13;

    // admins
    const TREVOR_REESE          = 14;
    const ALARESS_ADMIN         = 19;


    /**
     * Redis client for retrieving stored notifications.
     */
    protected ClientInterface $redis;

    /**
     * Redis client for publishing to queues.
     */
    private ClientInterface $redisQueue;

    /** @var User[] */
    private $userCache;

    /** @var ListFactory */
    private $listFactory;

    /**
     * How many currently active workers are there?
     *
     * When this counts down to zero, the test's finish state is checked.
     * (Each test should define its own finish state.)
     *
     * @var int
     */
    private $activeWorkers = 0;

    /**
     * Some tests have multiple stages; what is the current stage?
     *
     * @var int
     */
    private $currentStage = 1;

    /**
     * How many tests have failed?
     *
     * @var int
     */
    private $failureCount = 0;

    /**
     * @param string $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        // TODO should this be the cache or storage?
        $this->redis = Redis::getStorageClientInstance();
        $this->redisQueue = Redis::getSignalQueueClientInstance();
        $this->listFactory = new ListFactory(INSTANCE_ID, $this->redis);
    }

    /**
     * Is this test complete?
     *
     * @return bool
     */
    abstract protected function isTestComplete();

    /**
     * Goes to a stage of the test.
     *
     * @param int $stage
     */
    abstract protected function goToStage($stage);

    /**
     * Ends the test.
     */
    protected function markTestComplete()
    {
        if ($this->failureCount > 0) {
            $this->log->alert("Test ended with failures");
        } else {
            $this->log->warning("Test ended, no failures recorded; manual check for success required");
        }
        parent::markTestComplete();
    }

    /**
     * Increment the number of workers when one is tasked.
     *
     * @routed Tasking \d+ with route
     */
    public function onWorkerTasked()
    {
        ++$this->activeWorkers;
    }

    /**
     * Decrement the number of workers when one finishes.
     *
     * Also triggers stage transition, and test completion if it's complete.
     *
     * @routed Task Complete
     */
    public function onWorkerComplete()
    {
        --$this->activeWorkers;

        if ($this->activeWorkers > 0) {
            $this->log->info("Waiting for $this->activeWorkers more workers to finish");
            return;
        }

        if ($this->activeWorkers < 0) {
            $this->log->error("WTF?!");
            return;
        }

        if ($this->isTestComplete()) {
            $this->markTestComplete();
            return;
        }

        // go to the next stage
        $this->goToStage(++$this->currentStage);
    }

    /**
     * @param int $userId
     * @return Notification[]
     */
    protected function fetchNotifications($userId)
    {
        // load the data into your hat, and pull out a rabbit
        $decorator = new Decorator();

        $this->listFactory->listed($userId)->fetchIntoBundle($decorator);

        return $decorator->getNotifications();
    }


    /**
     * Fetch the given user, either from cache or from DB
     *
     * @param int $id
     * @return User
     */
    protected function getUser($id)
    {
        if (empty($this->userCache[$id])) {
            $this->userCache[$id] = UserMapper::getInstance()->findById($id);
        }
        return $this->userCache[$id];
    }


    /**
     * @param string $messageId
     * @throws OutOfBoundsException
     * @return Instance
     */
    protected function fetchMessageInstance($messageId)
    {
        $key  = sprintf('msg:%s:m:%s', INSTANCE_ID, $messageId);

        $compressed = $this->redis->get($key);

        if (!$compressed) {
            // no data
            throw new OutOfBoundsException("Message $messageId does not exist");
        }

        return unserialize(gzuncompress($compressed));
    }


    /**
     * @param string $daemon
     * @param string $signal
     * @param string $payload
     */
    protected function sendSignal($daemon, $signal, $payload = '')
    {
        $this->redisQueue->publish(sprintf('%s.%s.%s', $daemon, INSTANCE_ID, $signal), $payload);
    }


    /**
     * @return void
     */
    protected function weaponsToMaximum()
    {
        `mysql schoolbox < /usr/share/schoolbox/sql/notifications-all-instant.sql 2>&1`;
        $this->log->notice('Weapons to maximum');
    }


    /**
     * @return void
     */
    protected function clearRedis()
    {
        $this->log->notice(
            trim(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`) . ' redis queues cleared'
        );
    }


    /**
     * @return void
     */
    protected function clearRabbit()
    {
        $queuesToClear = [ INSTANCE_ID . '.sb.sched', 'sb.schedule' ];

        foreach ($queuesToClear as $q) {
            `rabbitmqadmin get queue=$q count=10000 requeue=false`;
        }

        $this->log->notice('Rabbit schedule queue cleared');
    }


    /**
     * @throws \UnderflowException
     * @return void
     */
    protected function cacheSubscriptions()
    {
        $loaderFactory = new Subscription\Loader\Factory($this->redis);
        $loaderFactory->setLog($this->log);

        // TODO replace with $di('subscriptionCache')
        // (I'd inject the queue instance as well, but the standard Redis
        //  instance is created with getClientInstance too)
        $cache = new WriteCache(
            $this->redis,
            $loaderFactory,
            $this->redisQueue,
        );
        $cache->setLog($this->log)->cacheAllUsers(INSTANCE_ID);
    }

    /**
     * Adds a failure.
     */
    protected function incrementFailureCount()
    {
        $this->failureCount++;
    }

    /**
     * Have any failures been recorded?
     *
     * @return bool
     */
    protected function isTestFailed()
    {
        return $this->failureCount > 0;
    }
}
