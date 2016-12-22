<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Performance;


use Schoolbox\Mapper;

interface IMonitor
{

    /**
     * @param Mapper $mapper
     * @return void
     */
    public function cacheIndices(Mapper $mapper);


    /**
     * @param Mapper\PredicateBuilder $predicate
     * @param string $selectFrom
     * @return void
     */
    public function assessPredicate(Mapper\PredicateBuilder $predicate, $selectFrom);


    /**
     * @param Mapper                  $mapper
     * @param Mapper\PredicateBuilder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, Mapper\PredicateBuilder $predicateBuilder);


    /**
     * @param Mapper                  $mapper
     * @param Mapper\PredicateBuilder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, Mapper\PredicateBuilder $predicateBuilder);

}
