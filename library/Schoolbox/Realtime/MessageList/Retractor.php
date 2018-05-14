<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use BadMethodCallException;
use Predis\ClientInterface;

/**
 * A user-independent list interface, usable only for retracting messages.
 */
class Retractor extends AbstractList
{
    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     */
    public function __construct(ClientInterface $redis, $instanceId)
    {
        parent::__construct($redis, $instanceId, 0);
    }


    /**
     * "Pushing" a message onto this queue only retracts it from other queues.
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance)
    {
        $this->redis->del([$this->getRedisMsgKey($instance)]);
    }


    /**
     * Retract queues are user-independent, therefore it has no access to any
     * user's set queue.
     *
     * @throws BadMethodCallException
     */
    protected function getSetKeyPattern()
    {
        throw new BadMethodCallException('Retract queues has no access to set');
    }


    /**
     * Retract queues have no access to any set queue, the suffix is not used.
     *
     * @throws BadMethodCallException
     */
    protected function getSuffix()
    {
        throw new BadMethodCallException('Retract queues do not support suffix');
    }
}
