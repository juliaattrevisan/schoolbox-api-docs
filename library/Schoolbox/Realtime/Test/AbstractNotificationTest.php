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
use Schoolbox\User;
use Schoolbox\UserMapper;

abstract class AbstractNotificationTest extends AbstractTest
{
    /* COMMON USERS*/

    // the Rhodes family
    const DAVID_RHODES          = 230;
    const CALEB_RHODES          = 245;
    const JO_RHODES             = 75;
    const MADDISON_RHODES       = 206;
    const PETER_RHODES          = 74;
    const RACHEL_RHODES         = 229;

    // the Saxon family
    const ANGELO_SAXON          = 226;
    const ANITA_SAXON           = 17;
    const LOUISE_SAXON          = 20;
    const SOPHIA_SAXON_TEACHER  = 160;
    const SOPHIA_SAXON_PARENT   = 244;

    // various teachers
    const GLENDA_SMITH          = 157;
    const JACKIE_BENSON         = 247;
    const ZANE_SMITH            = 151;
    const GARY_GEORGE           = 153;
    const ANNE_SMITH            = 159;
    const RYAN_VOLLMER          = 13;

    // admins
    const TREVOR_REESE          = 14;
    const ALARESS_ADMIN         = 19;


    /** @var \Predis\ClientInterface */
    private $redis;

    /** @var Connector */
    private $amqp;

    /** @var User[] */
    private $userCache;


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
     * Fetch the given user, either from cache or from DB
     *
     * @param int $id
     * @return User
     */
    protected function getUser($id)
    {
        if (empty($this->userCache[$id])) {
            $this->userCache[$id] = UserMapper::getInstance()->findById($id);
        }
        return $this->userCache[$id];
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
