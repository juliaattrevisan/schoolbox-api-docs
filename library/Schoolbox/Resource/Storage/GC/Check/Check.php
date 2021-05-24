<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\GC\Check;

interface Check
{

    /**
     * Takes an array of hashes, and returns the subset that this check regards as 'stale'
     * @param array $previous
     * @return array
     */
    public function getStale(array $previous);
}
