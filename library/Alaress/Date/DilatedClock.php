<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Date;

class DilatedClock implements IClock
{
    /** @var int */
    protected $start;

    /** @var int */
    protected $offset;

    /** @var float */
    protected $dilation;


    /**
     * The time dilation argument is probably the inverse of what you would intuitively expect it to be:
     *  - a dilation factor of 2 means the clock will move at HALF the speed of realtime
     *  - a dilation factor of 1/6 means the clock will move SIX TIMES the speed of realtime
     *
     * But to 'dilate' is to widen, so just roll with it, k? ;)
     *
     * @param float  $dilation
     * @param string $now
     */
    public function __construct($dilation = 1.0, $now = 'now')
    {
        $this->dilation = $dilation;
        $this->start    = microtime(true);
        $this->offset   = strtotime($now) - $this->start;
    }


    /**
     * @return int
     */
    public function time()
    {
        // obtain reference to true time
        $true = microtime(true);

        // obtain seconds elapsed since start
        $trueElapsed = $true - $this->start;

        $dilatedElapsed = $trueElapsed / $this->dilation;

        return (int)($this->start + $dilatedElapsed + $this->offset);
    }
}
