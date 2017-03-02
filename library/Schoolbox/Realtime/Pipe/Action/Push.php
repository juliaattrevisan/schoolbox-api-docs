<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Pipe\Action;

use Schoolbox\Realtime\Pipe\PipeManager;

class Push extends AbstractAction
{

    /**
     * @param PipeManager $manager
     * @return void
     */
    public function execute(PipeManager $manager)
    {
        $manager->transactUser(
            $this->getUserId(),
            $this->getMessageInstance()->getBaseHash(),
            $this->getMessageInstance()
        );
    }

}