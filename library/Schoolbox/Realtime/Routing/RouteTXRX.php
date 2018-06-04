<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\QueueInterface;
use Alaress\WorkerCrew\Transaction\ITransaction;
use Alaress\WorkerCrew\TXRX;
use BadMethodCallException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\UserMapper;

class RouteTXRX implements TXRX
{

    /** @var UserMapper */
    private $mapper;

    /** @var QueueSorter */
    private $sorter;

    /** @var ListFactory */
    private $listFactory;

    /**
     * Constructor
     *
     * @see `$this->buildTransmitter`
     * @see `$this->buildReceiver`
     *
     * @param UserMapper|null  $mapper
     * @param QueueSorter|null $sorter
     * @param ListFactory|null $listFactory
     */
    private function __construct(
        UserMapper $mapper = null,
        QueueSorter $sorter = null,
        ListFactory $listFactory = null
    ) {
        $this->mapper      = $mapper;
        $this->sorter      = $sorter;
        $this->listFactory = $listFactory;
    }


    /**
     * "Constructor" for the transmitter variant of this object, as used in
     * daemons.
     *
     * @param ListFactory $listFactory
     * @return self
     */
    public static function buildTransmitter(ListFactory $listFactory): self
    {
        return new static(null, null, $listFactory);
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
        if (null === $this->listFactory) {
            throw new BadMethodCallException('Attempting to send using the receiver variant of RouteTXRX');
        }

        // Is a retract single: retract it
        if ($instance->isActionRetractSingle()) {
            $this->listFactory->retract()->push($instance);
            return;
        }

        // Is a retract all: find all like it, then retract them
        if ($instance->isActionRetractAll()) {
            $this->listFactory->retractLike()->push($instance);
            return;
        }

        // Is a send action: let worker handle it
        if ($instance->isActionSend()) {
            if (!\is_array($userIds)) {
                throw new InvalidArgumentException('Payload must be an array of user IDs');
            }

            $key = $instance->getBaseHash().$instance->getParamHash();

            // rather than dump everyone in together, allow the transaction to batch the users
            // the implication of this is that if the user count exceeds the limit placed on the transaction,
            // users will be batched through multiple transactions
            foreach ($userIds as $userId) {
                $tx->put("$key-$userId", [ $instance, (int)$userId ]);
            }
            return;
        }

        throw new InvalidArgumentException(sprintf('Instance has unknown action type "%s"', $instance->getAction()));
    }


    /**
     * EXECUTED WORKER-SIDE
     * Receive and process the work sent from the daemon
     *
     * @param array $args
     * @return int
     * @throws BadMethodCallException if using the transmitter variant
     * @throws \Schoolbox\Mapper\Exception if user doesn't exist or was deleted
     */
    public function receive(array $args)
    {
        if (null === $this->mapper || null === $this->sorter) {
            throw new BadMethodCallException('Attempting to receive using the transmitter variant of RouteTXRX');
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
