<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule;

use Alaress\Scheduler\Event\EventInterface;
use Schoolbox\Mapper;

interface CrudEventInterface extends EventInterface
{

    /**
     * @return Mapper
     */
    public function getMapper();

}