<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Date;

/**
 * Class Clock
 *
 * This is the 'Standard Time' implementation of IClock.
 *
 * @package Alaress\Date
 */
class Clock implements IClock
{

    /** @var int */
    protected $offset;


    /**
     * @param string $now
     */
    public function __construct($now = 'now')
    {
        $this->offset = time() - strtotime($now);
    }

    /**
     * @return int
     */
    public function time()
    {
        // yes, this is ridiculously pithy, but sysclock is an external dependency,
        // and at least for testing, we need to be able to decouple from it.
        return time() - $this->offset;
    }
}
