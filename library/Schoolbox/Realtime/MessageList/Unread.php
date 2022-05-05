<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis;
use Schoolbox\Realtime\Pipe\PipeManager;

class Unread extends AbstractList
{
    /**
     * @return string
     */
    protected function getSuffix()
    {
        return 'unread';
    }


    /**
     * Push the given instance onto this queue
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance)
    {
        parent::push($instance);

        // in addition to whatever our parent is doing, we also want to advise the relevant user
        // that this message has been published, so it can be pushed to them
        $topic = "{$this->getNamespace()}:{$this->getUserId()}:" . PipeManager::PIPE_METHOD_PUSH;

        // TODO this should be injected: however I'd have to update everywhere
        //      which uses `ListFactory` to make it so

        Redis::getSignalQueueClientInstance()->publish($topic, "$instance");
    }
}
