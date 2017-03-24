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
use Schoolbox\UserMapper;

class RouteTXRX implements TXRX
{

    /** @var UserMapper */
    private $mapper;

    /** @var QueueSorter */
    private $sorter;


    /**
     * You need to install these dependencies on the worker-side; they not used daemon-side
     *
     * @param UserMapper  $mapper
     * @param QueueSorter $sorter
     */
    public function __construct(UserMapper $mapper = null, QueueSorter $sorter = null)
    {
        $this->mapper = $mapper;
        $this->sorter = $sorter;
    }


    /**
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