<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async\Event;

class Message extends AbstractEvent
{

    /** @var string */
    protected $channel;

    /**
     * @param string $payload
     * @param string $channel
     */
    public function __construct($payload, $channel)
    {
        parent::__construct($payload);
        $this->channel = $channel;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }
}
