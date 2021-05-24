<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Event;

use Alaress\Scheduler\Event\EventInterface;
use Schoolbox\Mapper;

interface CrudEventInterface extends EventInterface
{

    /**
     * @return Mapper
     */
    public function getMapper();
}
