<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Performance;


use Psr\Log\AbstractLogger;
use Schoolbox\Mapper;

class LiveMonitor extends AbstractLogger implements IMonitor
{

    /**
     * @param Mapper $mapper
     * @return void
     */
    public function cacheIndices(Mapper $mapper)
    {
        // noop
    }


    /**
     * @param Mapper\PredicateBuilder $predicate
     * @param string                  $selectFrom
     * @return void
     */
    public function assessPredicate(Mapper\PredicateBuilder $predicate, $selectFrom)
    {
        // noop
    }


    /**
     * @param Mapper                  $mapper
     * @param Mapper\PredicateBuilder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, Mapper\PredicateBuilder $predicateBuilder)
    {
        // noop
    }


    /**
     * @param Mapper                  $mapper
     * @param Mapper\PredicateBuilder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, Mapper\PredicateBuilder $predicateBuilder)
    {
        // noop
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        // noop
    }

}
