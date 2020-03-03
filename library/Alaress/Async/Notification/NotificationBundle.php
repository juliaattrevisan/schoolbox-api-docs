<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Alaress\Async\Notification;

use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\L10n\RosettaStoneFactory;

/**
 * Class NotificationBundle
 *
 * @package Alaress\Async\Notification
 */
class NotificationBundle
{

    /** @var Notification[] */
    protected $notifications;


    /**
     * Allows you to localise terminology
     *
     * @var RosettaStone
     */
    protected $rosettaStone;


    /**
     * Construct
     */
    public function __construct()
    {
        $this->notifications = [];

        /* I did consider injecting this, but the threat of destablising everything seemed a bit much
         * to do without consultation
         */
        $this->rosettaStone = (new RosettaStoneFactory())->build(L10nMapper::getInstance());
    }


    /**
     * @param Instance[] $instances
     * @throws InvalidArgumentException
     * @return $this
     */
    public function appendInstances($instances)
    {
        foreach ($instances as $instance) {
            if ($instance instanceof Instance) {
                $this->appendInstance($instance);
            }
            // TODO log non-instances
        }
        return $this;
    }


    /**
     * @param Instance $instance
     * @throws InvalidArgumentException
     * @return $this
     */
    public function appendInstance(Instance $instance)
    {
        $hash = $instance->getBaseHash();

        if (isset($this->notifications[$hash])) {
            $this->notifications[$hash]->appendInstance($instance);
        } else {
            $this->notifications[$hash] = new Notification([$instance]);
        }
        return $this;
    }


    /**
     * @param Notification[] $notifications
     * @return $this
     */
    public function appendNotifications($notifications)
    {
        foreach ($notifications as $n) {
            $this->appendNotification($n);
        }
        return $this;
    }


    /**
     * @param Notification $notification
     * @return $this
     */
    public function appendNotification($notification)
    {
        // no duplicates
        if (!isset($this->notifications[$notification->getId()])) {
            $this->notifications[$notification->getId()] = $notification;
        }
        return $this;
    }

    /**
     * @param int $limit
     * @param int $dir
     * @return Notification[]
     */
    public function getNotifications($limit = 0, $dir = SORT_DESC)
    {
        $this->sort($dir);
        return array_slice($this->notifications, 0, $limit ?: null, false);
    }


    /**
     * @return int
     */
    public function count()
    {
        return count($this->notifications);
    }


    /**
     * Sort the contained notifications
     *
     * @param int $dir
     * @return $this
     */
    protected function sort($dir = SORT_DESC)
    {
        usort($this->notifications, function ($a, $b) use ($dir) {
            /**
             * @var Notification $a
             * @var Notification $b
             */
            $dir   = $dir == SORT_DESC ? -1 : 1;
            $atime = $a->getTimestamp();
            $btime = $b->getTimestamp();

            return $dir * ($atime == $btime ? 0 : ($atime > $btime ? 1 : -1));
        });

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     */
    protected function limit($limit, $offset = 0)
    {
        $this->notifications = array_slice($this->notifications, $offset, $limit, false);
    }

}
