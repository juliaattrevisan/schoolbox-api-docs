<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
