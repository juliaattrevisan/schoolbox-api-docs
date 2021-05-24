<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\WorkerCrew\Transaction\ITransaction;
use Alaress\WorkerCrew\TXRX;
use BadMethodCallException;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
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

    // constants to define request types
    private const TYPE_USER = 'user';
    private const TYPE_OBJECT = 'object';
    private const TYPE_RETRACT = 'retract';

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
     * {@inheritDoc}
     */
    public function send($instance, ITransaction $tx, $userIds)
    {
        if (null === $this->listFactory) {
            throw new BadMethodCallException('Attempting to send using the receiver variant of RouteTXRX');
        }
        /** @var Instance $instance */
        $key = $instance->getBaseHash() . $instance->getParamHash();

        // Is a retract action
        if ($instance->isActionRetract()) {
            $tx->put("{$key}-retract", [$instance, null, self::TYPE_RETRACT]);
            return;
        }

        // Is a send action
        if ($instance->isActionSend()) {
            if (!\is_array($userIds)) {
                throw new InvalidArgumentException('Payload must be an array of user IDs');
            }

            // If event object id is set, put request to add instance to the object set
            if (!empty($instance->getEventObjectId())) {
                $tx->put("{$key}-object", [$instance, null, self::TYPE_OBJECT]);
            }

            // rather than dump everyone in together, allow the transaction to batch the users
            // the implication of this is that if the user count exceeds the limit placed on the transaction,
            // users will be batched through multiple transactions
            foreach ($userIds as $userId) {
                $tx->put("$key-$userId", [$instance, (int)$userId, self::TYPE_USER]);
            }
            return;
        }

        throw new InvalidArgumentException(sprintf('Instance has unknown action type "%s"', $instance->getAction()));
    }


    /**
     * EXECUTED WORKER-SIDE
     * Receive and process the work sent from the daemon
     *
     * {@inheritDoc}
     */
    public function receive(array $args)
    {
        if (null === $this->mapper || null === $this->sorter) {
            throw new BadMethodCallException('Attempting to receive using the transmitter variant of RouteTXRX');
        }

        $count = 0;

        /**
         * @var  Instance $instance
         * @var  int $userId
         * @var  string $type
         */
        foreach ($args as [$instance, $userId, $type]) {
            $count = 0;
            switch ($type) {
                case self::TYPE_USER:
                    // this is the payload from $tx->put() above
                    try {
                        $user = $this->mapper->findById($userId);
                    } catch (MapperException $e) {
                        // User is missing?
                        // Tell the sorter to handle that somehow
                        $this->sorter->processForMissingUser($userId, $instance);
                        break;
                    }

                    // if need to retract existing messages first, do so.
                    if ($instance->retractFirst()) {
                        // no need to clone, but just in case
                        $retractInstance = clone $instance;
                        $retractInstance->setAction(Instance::ACTION_RETRACT_ALL);
                        $this->sorter->processRetract($retractInstance);
                        // process after retracting
                        $count += $this->sorter->process($user, $instance) ? 1 : 0;
                        // re-add to object list, so that this can be retracted next time
                        $this->sorter->processEventObjectList($instance);
                        break;
                    }
                    $count += $this->sorter->process($user, $instance) ? 1 : 0;
                    break;
                case self::TYPE_RETRACT:
                    $this->sorter->processRetract($instance);
                    break;
                case self::TYPE_OBJECT:
                    $this->sorter->processEventObjectList($instance);
                    break;
                default:
            }
        }

        return $count;
    }
}
