<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;

interface IPublicationFactory extends IListFactory
{

    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function mobile($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function email($userId);
}
