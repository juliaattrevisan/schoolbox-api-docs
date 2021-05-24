<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe\Action;

use Alaress\WorkerCrew\QueueingException;
use InvalidArgumentException;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Pipe\PipeManager;

class Alert extends AbstractAction
{

    /**
     * @param PipeManager $manager
     * @throws QueueingException
     * @throws InvalidArgumentException
     * @return void
     */
    public function execute(PipeManager $manager)
    {
        $manager->enqueueUser(
            $this->getUserId(),
            PipeManager::PIPE_METHOD_ALERT,
            (new Decorator())->appendInstance($this->getMessageInstance())->renderAsSocketArray(true)
        );
    }
}
