<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\AMQP\Bunny;

interface ControlInterface
{

    /**
     * @param array $fields
     * @return array
     */
    public function listQueues($fields);

    /**
     * @param array $fields
     * @return array
     */
    public function listExchanges($fields);

    /**
     * @param array $fields
     * @return array
     */
    public function listBindings($fields);

    /**
     * @param array $fields
     * @return array
     */
    public function listConnections($fields);

    /**
     * @param array $fields
     * @return array
     */
    public function listChannels($fields);

    /**
     * @return array
     */
    public function listConsumers();
}
