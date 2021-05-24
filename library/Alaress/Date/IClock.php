<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Date;

/**
 * Interface IClock
 *
 * An interface befitting a Time Lord: implement this class to manipulate time as you see fit.
 * 'Standard Time' is 1 sec / sec from Epoch, but there's nothing stopping you from accelerating, slowing, freezing or simply changing time.
 * (Reversing time is entirely possible, but not recommended; non-linear and non-positive time will have unpredictable effects...)
  *
 * @package Alaress\Date
 */
interface IClock
{

    /**
     * Return the current time, as represented by this Clock.
     * Any positive integer is acceptable.
     *
     * @return int
     */
    public function time();
}
