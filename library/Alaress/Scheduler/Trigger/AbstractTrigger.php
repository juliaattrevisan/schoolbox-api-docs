<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;

abstract class AbstractTrigger implements TriggerInterface
{

    /** @var string */
    protected $label;

    /** @var EventInterface */
    protected $event;


    /**
     * Return the trigger label that this trigger is based on
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }


    /**
     * Return the event that precipitated this trigger
     *
     * @return EventInterface
     */
    public function getEvent()
    {
        return $this->event;
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
        $object      = $this->getEvent()->getObject();
        $getIdMethod = $this->determineGetIdMethod();
        if (method_exists($object, $getIdMethod)) {
            return sprintf("%s:%s", get_class($object), $object->$getIdMethod());
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
        $object      = $this->getEvent()->getObject();
        $getIdMethod = $this->determineGetIdMethod();
        $key         = sprintf("%s:%s", get_class($object), $this->getLabel());
        if (method_exists($object, $getIdMethod)) {
            $key .= sha1(sprintf("%s:%s", get_class($object), $object->$getIdMethod()));
        } else {
            // fall back to using the SPL hash if there's no available ID
            $key .= spl_object_hash($object);
        }
        return $key;
    }

    /**
     * What method can I use to get the ID of this trigger's object?
     *
     * @return string
     */
    private function determineGetIdMethod(): string
    {
        $event       = $this->getEvent();
        $getIdMethod = 'getId';
        if ($event instanceof CrudEventInterface && $event->getMapper() && $event->getMapper()->hasIdentifier()) {
            $getIdMethod = $event->getMapper()->getGetIdentifierMethodName();
        }
        return $getIdMethod;
    }
}
