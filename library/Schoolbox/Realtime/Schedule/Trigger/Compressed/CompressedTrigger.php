<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Compressed;

use Alaress\Scheduler\Event\EventInterface;
use RuntimeException;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\InstantTriggerInterface;

class CompressedTrigger implements InstantTriggerInterface
{

    /** @var string */
    protected $serial;

    /** @var InstantTriggerInterface */
    protected $trigger;

    /**
     * @param InstantTriggerInterface $instantTrigger
     * @param int $compression
     */
    public function __construct($instantTrigger, $compression)
    {
        $this->serial = gzcompress(serialize($instantTrigger), $compression);
        $this->deflate();
    }


    /**
     * Remove cached expansion
     */
    public function deflate()
    {
        $this->trigger = null;
    }


    /**
     * Inflate and cache the result
     *
     * @throws RuntimeException
     * @return InstantTriggerInterface
     */
    public function extractTrigger()
    {
        if (empty($this->trigger)) {
            $this->trigger = unserialize(gzuncompress($this->serial));
        }
        return $this->trigger;
    }


    /**
     * @return AbstractModuleNotifier
     */
    public function getModuleNotifier()
    {
        return $this->extractTrigger()->getModuleNotifier();
    }


    /**
     * Return the timestamp at which this trigger should fire (can be in the past)
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->extractTrigger()->getTimestamp();
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return $this->extractTrigger()->getCallbacks();
    }


    /**
     * Return the trigger label that this trigger is based on
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->extractTrigger()->getLabel();
    }


    /**
     * Return the event that precipitated this trigger
     *
     * @return EventInterface
     */
    public function getEvent()
    {
        return $this->extractTrigger()->getEvent();
    }


    /**
     * Return a representation of self in the form "objectClass:label"
     *
     * @return string
     */
    public function getIdString()
    {
        return sprintf("%s:%s", get_class($this->getEvent()->getObject()), $this->getLabel());
    }


    /**
     * Return a hash representation of self
     *
     * @return string
     */
    public function getHash()
    {
        return spl_object_hash($this);
    }


    /**
     * Return an identifier of attached event object, if available
     *
     * @return string
     */
    public function getEventObjectId(): string
    {
        $object = $this->getEvent()->getObject();
        if (method_exists($object, 'getId')) {
            return sprintf("%s:%s", get_class($object), $object->getId());
        }
        return '';
    }


    /**
     * Return a hash representation of attached event object
     * This is using the same way as the key for AbstractScheduler->timers
     *
     * @return string
     */
    public function getEventObjectHash(): string
    {
        $object = $this->getEvent()->getObject();
        $key = sprintf("%s:%s", get_class($object), $this->getLabel());
        if (method_exists($object, 'getId')) {
            $key .= sha1(sprintf("%s:%s", get_class($object), $object->getId()));
        } else {
            // fall back to using the SPL hash if there's no available ID
            $key .= spl_object_hash($object);
        }
        return $key;
    }
}
