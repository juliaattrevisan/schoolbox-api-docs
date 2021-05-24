<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Performance;

use Alaress\Database\Predicate\Builder;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

interface IMonitor extends LoggerInterface
{

    /**
     * @param Mapper $mapper
     * @return void
     */
    public function cacheIndices(Mapper $mapper);


    /**
     * @param Builder $predicate
     * @param string  $selectFrom
     * @return void
     */
    public function assessPredicate(Builder $predicate, $selectFrom);


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder);


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder);
}
