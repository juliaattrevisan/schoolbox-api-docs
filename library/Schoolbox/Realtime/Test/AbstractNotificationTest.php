<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Test;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Async\Test\AbstractTest;
use Alaress\Redis;
use OutOfBoundsException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\UserQueue\InstantUserQueue;
use Schoolbox\Realtime\Notification\Decorator;

abstract class AbstractNotificationTest extends AbstractTest
{
    /** @var \Predis\ClientInterface */
    private $redis;

    /** @var Connector */
    private $amqp;


    /**
     * @param string $name
     * @param array  $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->redis = Redis::getClientInstance();
        $this->amqp  = Connector::getInstance();
    }


    /**
     * @param int $userId
     * @return Notification[]
     */
    protected function fetchNotifications($userId)
    {
        $queue = new InstantUserQueue($this->redis, $this->amqp, $userId);

        // load the data into your hat, and pull out a rabbit
        $decorator = new Decorator();
        $queue->fetchNotificationsIntoBundle($decorator);

        return $queue->fetchNotifications(-1);
    }


    /**
     * @param string $messageId
     * @throws OutOfBoundsException
     * @return Instance
     */
    protected function fetchMessageInstance($messageId)
    {
        $key  = sprintf('msg:%s:m:%s', INSTANCE_ID, $messageId);

        $compressed = Redis::getClientInstance()->get($key);

        if (!$compressed) {
            // no data
            throw new OutOfBoundsException("Message $messageId does not exist");
        }

        return Instance::jsonUnserialize(gzuncompress($compressed));
    }
}
