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
