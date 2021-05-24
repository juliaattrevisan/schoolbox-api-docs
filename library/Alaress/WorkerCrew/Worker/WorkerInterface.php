<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
