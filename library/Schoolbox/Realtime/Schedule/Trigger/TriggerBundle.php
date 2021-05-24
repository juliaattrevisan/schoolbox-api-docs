<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Realtime\Schedule\Event\Event;

class TriggerBundle implements TriggerBundleInterface
{

    /** @var TriggerFactory */
    protected $factory;

    /** @var string */
    protected $method;

    /** @var TriggerInterface[] */
    protected $triggers;


    /**
     * @param TriggerFactory $factory
     * @param string $method
     */
    public function __construct($factory, $method)
    {
        $this->factory = $factory;
        $this->method = $method;
        $this->triggers = array();
    }


    /**
     * @return TriggerInterface[]
     */
    public function getTriggers()
    {
        return $this->triggers;
    }


    /**
     * @param string $label
     * @param int|callable $timestamp
     */
    public function attach($label, $timestamp)
    {
        $this->attachEach(array($label => $timestamp));
    }


    /**
     * @param array $array
     */
    public function attachEach($array)
    {
        $ts = $this->factory->build($array);
        foreach ($ts as $t) {
            $this->triggers[$t->getLabel()] = $t;
        }
    }


    /**
     * @param string $label
     */
    public function detach($label)
    {
        $this->detachEach(array($label));
    }


    /**
     * @param array $array
     */
    public function detachEach($array)
    {
        $ts = $this->factory->cancel($array);
        foreach ($ts as $t) {
            $this->triggers[$t->getLabel()] = $t;
        }
    }


    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return Event
     */
    public function getEvent()
    {
        return $this->factory->getEvent();
    }
}
