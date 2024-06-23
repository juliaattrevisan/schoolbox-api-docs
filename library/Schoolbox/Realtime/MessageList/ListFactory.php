<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\Composite;
use Alaress\Async\Queue\QueueInterface;
use Alaress\Redis;
use Predis\ClientInterface;

/**
 * Factory for building message queues.
 */
class ListFactory implements IListFactory
{
    /**
     * Constructor
     *
     * @param string          $instanceId The instance for which this factory is created.
     * @param ClientInterface $redis      Redis client: data store for messages.
     */
    public function __construct(private string $instanceId, private ClientInterface $redis)
    {
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function listed($userId)
    {
        return new Listed($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId)
    {
        return new Unread($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId)
    {
        return new Digest($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function instant($userId)
    {
        return new Composite($this->listed($userId), $this->unread($userId));
    }


    /**
     * @inheritdoc
     */
    public function retract(): QueueInterface
    {
        return new Retractor($this->redis, $this->instanceId, Retractor::MODE_RETRACT_SINGLE);
    }


    /**
     * @inheritdoc
     */
    public function retractLike(): QueueInterface
    {
        return new Retractor($this->redis, $this->instanceId, Retractor::MODE_RETRACT_LIKE);
    }


    /**
     * @inheritdoc
     */
    public function eventObjectList($eventObjectId): QueueInterface
    {
        return new EventObjectList($this->redis, $this->instanceId, $eventObjectId);
    }


    /**
     * Array representation of object
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'instanceId' => $this->instanceId
        ];
    }


    /**
     * Constructs the object
     *
     * @param string|array $serialized Prior to 23.1 `serialize` always returned
     *                                 a string, now it returns an array.
     *                                 Need to handle both cases.
     * @return void
     */
    public function __unserialize(string|array $serialized): void
    {
        // AFAICT `$this->instanceId` will always just be the INSTANCE_ID const:
        // attempt to hydrate it anyway.
        if (is_string($serialized)) {
            $this->instanceId = $serialized;
        } else {
            $this->instanceId = $serialized['instanceId'];
        }

        // Messages are stored in the persistent Redis instance
        // TODO IoC
        $this->redis = Redis::getStorageClientInstance();
    }
}
