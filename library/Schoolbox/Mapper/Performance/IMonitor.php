<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Performance;


use Alaress\Database\PredicateBuilder\PredicateBuilder;
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
     * @param PredicateBuilder $predicate
     * @param string           $selectFrom
     * @return void
     */
    public function assessPredicate(PredicateBuilder $predicate, $selectFrom);


    /**
     * @param Mapper           $mapper
     * @param PredicateBuilder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, PredicateBuilder $predicateBuilder);


    /**
     * @param Mapper           $mapper
     * @param PredicateBuilder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, PredicateBuilder $predicateBuilder);

}
