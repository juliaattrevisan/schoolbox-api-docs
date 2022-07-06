<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use OutOfRangeException;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger;
use Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTriggerBundle;

class TriggerFactory
{
    /** @var ModuleNotifierInterface */
    protected $notifier;

    /** @var Event */
    protected $event;

    /** @var TriggerBundleInterface[] */
    protected $bundles;

    /** @var int */
    protected $level;

    /**
     * @param ModuleNotifierInterface $instance
     * @param EventInterface $event
     * @param int $compression
     */
    public function __construct(ModuleNotifierInterface $instance, EventInterface $event, $compression = -1)
    {
        $this->notifier = $instance;
        $this->event = $event;
        $this->level = $compression;
        $this->bundles = array();
    }


    /**
     * @param string $method
     * @return TriggerBundleInterface
     */
    public function buildTriggers($method)
    {
        $this->notifier->bundleTriggers($this->getBundle($method));
        return $this->getBundle($method);
    }


    /**
     * @param array $events
     * @param bool $disregardExpired
     * @return TriggerInterface[]
     */
    public function build($events, $disregardExpired = false)
    {
        $triggers = array();
        $now = time();

        // iterate across the events supplied and construct triggers out of them
        foreach ($events as $label => $time) {
            if (is_callable($time)) {
                // continue; // FIREFIGHT
                $t = new CallbackTrigger($label, $time, $this->event, $this->notifier);
            } else {
                $t = new InstantTrigger($label, $time ?: $now, $this->event, $this->notifier);
            }

            // if the timestamp is in the past, and we're disregarding expired, do not include in the return
            if (!($disregardExpired && $t->getTimestamp() < $now)) {
                $triggers[] = $t;
            }
        }
        return $triggers;
    }


    /**
     * @param string[] $events
     * @return CancelTrigger[]
     */
    public function cancel($events)
    {
        $triggers = array();
        foreach ($events as $label) {
            $triggers[] = new CancelTrigger($label, $this->event);
        }
        return $triggers;
    }


    /**
     * @param string $method
     * @return TriggerBundleInterface
     */
    public function getBundle($method)
    {
        $method = strtolower($method);
        if (!isset($this->bundles[$method])) {
            $this->bundles[$method] = $this->newBundle($method);
        }
        return $this->bundles[$method];
    }


    /**
     * If possible, cleans up triggers
     *
     * @param TriggerInterface[] $triggers
     */
    public function cleanup($triggers)
    {
        foreach ($triggers as $t) {
            if ($t instanceof CompressedTrigger) {
                $t->deflate();
            }
        }
    }

    /**
     * @param string $method
     * @return TriggerBundleInterface
     * @throws OutOfRangeException
     */
    protected function newBundle($method)
    {
        if ($this->level < 0) {
            return new TriggerBundle($this, $method);
        }
        if ($this->level <= 9) {
            return new CompressedTriggerBundle($this, $method, $this->level);
        }
        throw new OutOfRangeException("Invalid bundle compression given");
    }

    /**
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }
}
