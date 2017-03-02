<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Pipe\Action;

use Alaress\Async\Notification\Instance\Instance;

abstract class AbstractAction implements IAction
{
    /** @var int */
    private $userId;

    /** @var Instance */
    private $messageInstance;

    /**
     * @param int      $userId
     * @param Instance $messageInstance
     */
    public function __construct($userId, Instance $messageInstance)
    {
        $this->userId          = $userId;
        $this->messageInstance = $messageInstance;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return Instance
     */
    public function getMessageInstance()
    {
        return $this->messageInstance;
    }

}