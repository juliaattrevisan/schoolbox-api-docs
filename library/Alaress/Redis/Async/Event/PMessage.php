<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async\Event;

class PMessage extends Message
{
    /** @var string */
    protected $pattern;

    /**
     * @param string $payload
     * @param string $channel
     * @param string $pattern
     */
    public function __construct($payload, $channel, $pattern)
    {
        parent::__construct($payload, $channel);
        $this->pattern = $pattern;
    }


    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }
}
