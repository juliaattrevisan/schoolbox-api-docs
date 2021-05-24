<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Test;

use PHPUnit_Framework_AssertionFailedError;
use ReflectionMethod;

class Subscription
{

    /** @var array */
    private $conditions;
    /** @var array */
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

        foreach ($conditions as $daemon => $patterns) {
            foreach ($patterns as $pattern => $count) {
                $this->predicate["$daemon:$pattern"] = $count;
            }
        }

        ksort($this->predicate);

        $this->callback   = $callback;
        $this->testCase   = $testCase;
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

        $allCaptures = [];

        foreach ($hooks as $pattern => $captures) {
            $count = count($captures);

            foreach ($captures as $capture) {
                $allCaptures = array_merge($allCaptures, $capture);
            }

            $prehooks[$pattern] = $count;
        }

        foreach ($this->predicate as $expectedKey => $expectedCount) {
            // iterate the predicates and check that all have at least one hit
            if (empty($prehooks[$expectedKey])) {
                return false;
            }

            if ($prehooks[$expectedKey] < $expectedCount) {
                return false;
            }
        }

        $args = [];

        foreach ($this->callback->getParameters() as $param) {
            // align all regex captures to the arguments of the test case
            $name = $param->getName();
            if (isset($allCaptures[$name])) {
                $args[$name] = $allCaptures[$name];
            }
        }

        // any assertions in here will throw PHPUnit_Framework_AssertionFailedError
        // they will be caught be the framework
        $this->callback->invokeArgs($this->testCase, $args);

        return true;
    }
}
