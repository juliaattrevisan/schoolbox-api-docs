<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Test;


use PHPUnit_Framework_AssertionFailedError;
use ReflectionMethod;

class Subscription
{

    /** @var array */
    private $conditions;
    /** @var string */
    private $predicate;
    /** @var ReflectionMethod */
    private $callback;
    /** @var TestCase */
    private $testCase;

    /**
     * Subscription constructor.
     *
     * @param array            $conditions
     * @param TestCase         $testCase
     * @param ReflectionMethod $callback
     */
    public function __construct(array $conditions, TestCase $testCase, ReflectionMethod $callback)
    {
        $this->conditions = $conditions;

        $predicates = [];

        foreach ($conditions as $daemon => $patterns) {
            foreach ($patterns as $pattern => $count)
            $predicates[] = "$daemon:$pattern:$count";
        }

        sort($predicates);
        $this->predicate = implode(';', $predicates);

        $this->callback   = $callback;
        $this->testCase = $testCase;
    }


    /**
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }


    /**
     * @return string
     */
    public function getCallbackName()
    {
        return $this->callback->getName();
    }


    /**
     * @param  array $hooks
     * @throws PHPUnit_Framework_AssertionFailedError
     * @return bool
     */
    public function fire(array $hooks)
    {
        ksort($hooks);

        $prehooks = [];

        foreach ($hooks as $pattern => $count) {
            $prehooks[] = "$pattern:$count";
        }

        $hook = implode(';', $prehooks);

        $fire = $hook == $this->predicate;

        if ($fire) {
            // any assertions in here will throw PHPUnit_Framework_AssertionFailedError
            // they will be caught be the framework
            $this->callback->invoke($this->testCase);
        }

        return $fire;
    }
}
