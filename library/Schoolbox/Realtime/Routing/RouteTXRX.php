<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\WorkerCrew\Transaction\ITransaction;
use Alaress\WorkerCrew\TXRX;
use BadMethodCallException;
use InvalidArgumentException;
use PDOException;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\UserMapper;

class RouteTXRX implements TXRX
{

    /** @var UserMapper */
    private $mapper;

    /** @var QueueSorter */
    private $sorter;

    /** @var ClientInterface */
    private $redis;

    /** @var string */
    private $instanceId;


    /**
     * Constructor
     *
     * @see buildForDaemon
     * @see buildForWorker
     *
     * @param UserMapper|null      $mapper
     * @param QueueSorter|null     $sorter
     * @param ClientInterface|null $redis
     * @param string|null          $instanceId
     */
    private function __construct(
        UserMapper $mapper = null,
        QueueSorter $sorter = null,
        ClientInterface $redis = null,
        string $instanceId = null
    ) {
        $this->mapper     = $mapper;
        $this->sorter     = $sorter;
        $this->redis      = $redis;
        $this->instanceId = $instanceId;
    }


    /**
     * "Constructor" for the transactor variant of this object, as used in
     * daemons.
     *
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @return self
     */
    public static function buildTransactor(ClientInterface $redis, string $instanceId): self
    {
        return new static(null, null, $redis, $instanceId);
    }


    /**
     * "Constructor" for the receiver variant of this object, as used in
     * workers.
     *
     * @param UserMapper  $mapper
     * @param QueueSorter $sorter
     * @return self
     */
    public static function buildReceiver(UserMapper $mapper, QueueSorter $sorter): self
    {
        return new static($mapper, $sorter, null);
    }


    /**
     * EXECUTED DAEMON-SIDE
     * Send the work from the daemon to the worker
     *
     * @param Instance     $instance
     * @param ITransaction $tx
     * @param array        $userIds
     * @throws InvalidArgumentException
     * @throws BadMethodCallException if using the receiver variant
     * @return void
     */
    public function send($instance, ITransaction $tx, $userIds)
    {
        if (null === $this->redis) {
            throw new BadMethodCallException('Attempting to send using the receiver variant of RouteTXRX');
        }

        // Is a retract single: retract it
        if ($instance->isActionRetractSingle()) {
            $this->redis->del([$this->buildMessageKey($instance)]);
            return;
        }

        // Is a retract all: find all like it, then retract them
        if ($instance->isActionRetractAll()) {
            $msgKeys = [];
            foreach (new Keyspace($this->redis, $this->buildMessageBaseKey($instance)) as $msgKey) {
                $msgKeys[] = $msgKey;
            }
            if (!empty($msgKeys)) {
                $this->redis->del($msgKeys);
            }
            return;
        }

        // Is a send action: proceed as before
        if (!is_array($userIds)) {
            throw new InvalidArgumentException('Payload must be an array of user IDs');
        }

        $key = $instance->getBaseHash().$instance->getParamHash();

        // rather than dump everyone in together, allow the transaction to batch the users
        // the implication of this is that if the user count exceeds the limit placed on the transaction,
        // users will be batched through multiple transactions
        foreach ($userIds as $userId) {
            $tx->put("$key-$userId", [ $instance, (int)$userId ]);
        }
    }


    /**
     * EXECUTED WORKER-SIDE
     * Receive and process the work sent from the daemon
     *
     * @param array $args
     * @return int
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function receive(array $args)
    {
        if (null === $this->mapper || null === $this->sorter) {
            throw new BadMethodCallException('Attempting to receive using the transactor variant of RouteTXRX');
        }

        $count = 0;

        foreach ($args as list($instance, $userId)) {
            // this is the payload from $tx->put() above
            $user   = $this->mapper->findById($userId);
            $count += $this->sorter->process($user, $instance) ? 1 : 0;
        }

        return $count;
    }


    /**
     * Builds a message key for this instance, so that it may be retracted.
     *
     * TODO undupe this from RedisQueue
     *
     * @param Instance $instance
     * @return string
     */
    private function buildMessageKey(Instance $instance): string
    {
        return sprintf(
            'msg:%s:m:%s:%s',
            $this->instanceId,
            $instance->getBaseHash(),
            $instance->getParamHash()
        );
    }


    /**
     * Builds a base message key for this instance, so that all messages like
     * it may be retracted.
     *
     * TODO undupe this from RedisQueue
     *
     * @param Instance $instance
     * @return string
     */
    private function buildMessageBaseKey(Instance $instance): string
    {
        return sprintf(
            'msg:%s:m:%s:*',
            $this->instanceId,
            $instance->getBaseHash()
        );
    }
}
