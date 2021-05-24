<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe\Action;

use Schoolbox\Realtime\Pipe\PipeManager;

interface IAction
{

    /**
     * @return int
     */
    public function getUserId();

    /**
     * @param PipeManager $manager
     * @return void
     */
    public function execute(PipeManager $manager);
}
