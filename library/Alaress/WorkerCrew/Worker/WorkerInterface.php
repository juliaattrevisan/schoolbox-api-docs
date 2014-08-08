<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use InvalidArgumentException;
use RuntimeException;

interface WorkerInterface
{

    /**
     * @param string $buffer
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return string
     */
    public function parse($buffer);

}