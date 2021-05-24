<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Performance;

use Alaress\Database\Predicate\Builder;
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
     * @param Builder $predicate
     * @param string  $selectFrom
     * @return void
     */
    public function assessPredicate(Builder $predicate, $selectFrom)
    {
        // noop
    }


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder)
    {
        // noop
    }


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder)
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
