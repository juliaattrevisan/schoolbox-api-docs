<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use BadMethodCallException;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;

/**
 * A user-independent list interface, usable only for retracting messages.
 */
class Retractor extends AbstractList
{
    public const MODE_RETRACT_SINGLE = 1;
    public const MODE_RETRACT_LIKE = 2;

    /**
     * @var int
     */
    private $mode;

    /**
     * Constructor
     *
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @param int             $mode
     */
    public function __construct(ClientInterface $redis, $instanceId, int $mode)
    {
        if (!\in_array($mode, [self::MODE_RETRACT_SINGLE, self::MODE_RETRACT_LIKE], true)) {
            throw new \InvalidArgumentException('$mode must be one of the MODE_RETRACT_* class constants');
        }
        $this->mode = $mode;

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
        if ($this->mode === self::MODE_RETRACT_SINGLE) {
            $this->retractSingle($instance);
        } elseif ($this->mode === self::MODE_RETRACT_LIKE) {
            $this->retractLike($instance);
        }
        // should always be at least one of the above:
        // constructor guarantees mode must be one of the above modes
    }


    /**
     * Surgically retract this message from the queue, if it's there.
     *
     * @param Instance $instance
     * @return bool
     */
    private function retractSingle(Instance $instance): bool
    {
        // When retracting messages, only the message body need be removed.
        // The pointer in the set need not be removed, as `fetch()` knows how
        // to handle set items with missing message bodies.
        return $this->redis->del([$this->getRedisMsgKey($instance)]) > 0;
    }


    /**
     * Surgically retract this message and any with the same base hash from the
     * queue.
     *
     * @param Instance $instance
     * @return int The number of msgs removed
     */
    private function retractLike(Instance $instance): int
    {
        $ids = [];
        foreach (new Keyspace($this->redis, $this->getRedisMsgBaseKey($instance)) as $id) {
            $ids[] = $id;
        }

        if (!empty($ids)) {
            // Again, only the message body need be removed
            $this->redis->del($ids);
        }
        return \count($ids);
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
