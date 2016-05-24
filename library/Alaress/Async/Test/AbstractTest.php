<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Test;


use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;

abstract class AbstractTest extends PHPUnit_Framework_TestCase implements TestCase
{

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param LoggerInterface $log
     * @return void
     */
    public function attachLogger(LoggerInterface $log)
    {
        $this->log = $log;
    }

}
