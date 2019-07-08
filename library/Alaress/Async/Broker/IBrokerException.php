<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker;

interface IBrokerException
{
    /**
     * @return IResolver
     */
    public function getResolver();
}
