<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async\Event;

/**
 * Interface Event
 *
 * Exists primarily for type safety
 *
 * @package Alaress\Redis\Async\Event
 */
interface IEvent
{

    /**
     * @return string
     */
    public function getPayload();

}
