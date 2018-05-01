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
use Schoolbox\UserMapper;

class RouteTXRX implements TXRX
{

    /** @var UserMapper */
    private $mapper;

    /** @var QueueSorter */
    private $sorter;

    /** @var ClientInterface */
    private $redis;


    /**
     * @see buildForDaemon
     * @see buildForWorker
     * @param UserMapper|null      $mapper
     * @param QueueSorter|null     $sorter
     * @param ClientInterface|null $redis
     */
    private function __construct(
        UserMapper $mapper = null,
        QueueSorter $sorter = null,
        ClientInterface $redis = null
    ) {
        $this->mapper = $mapper;
        $this->sorter = $sorter;
        $this->redis  = $redis;
    }


    /**
     * "Constructor" for the transactor variant of this object, as used in
     * daemons.
     *
     * @param ClientInterface $redis
     * @return self
     */
    public static function buildTransactor(ClientInterface $redis): self
    {
        return new static(null, null, $redis);
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
     * TODO make $userIDs able to be a string command (retract, retractAllLike)
     * TODO handle retracts right here, instead of in receiver
     *
     * EXECUTED DAEMON-SIDE
     * Send the work from the daemon to the worker
     *
     * @param Instance     $instance
     * @param ITransaction $tx
     * @param array        $userIds
     * @throws InvalidArgumentException
     * @return void
     */
    public function send($instance, ITransaction $tx, $userIds)
    {
        if ($instance->isActionRetractSingle()) {
            // TODO handle retracts right here
        }
        if ($instance->isActionRetractAll()) {
            // TODO handle retracts
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
            throw new BadMethodCallException('Not configured');
        }

        $count = 0;

        foreach ($args as list($instance, $userId)) {
            // this is the payload from $tx->put() above
            $user   = $this->mapper->findById($userId);
            $count += $this->sorter->process($user, $instance) ? 1 : 0;
        }

        return $count;
    }

}
