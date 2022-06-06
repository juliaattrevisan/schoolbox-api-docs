<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;

class StubTriggerBundle implements TriggerBundleInterface
{

    /** @var Event */
    protected $event;

    /** @var string */
    protected $method;

    /**
     * @param Event $event
     * @param string $method
     */
    public function __construct($event, $method)
    {
        $this->event = $event;
        $this->method = $method;
    }

    /**
     * @param string $label
     * @param int    $timestamp
     */
    public function attach($label, $timestamp)
    {
        // noop
    }


    /**
     * @param array $array
     */
    public function attachEach($array)
    {
        // noop
    }


    /**
     * @param string $label
     */
    public function detach($label)
    {
        // noop
    }


    /**
     * @param array $array
     */
    public function detachEach($array)
    {
        // noop
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
        return $this->event;
    }

    /**
     * @return TriggerInterface[]
     */
    public function getTriggers()
    {
        // noop
        return [];
    }
}
