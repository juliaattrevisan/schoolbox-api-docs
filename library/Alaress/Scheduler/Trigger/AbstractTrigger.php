<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler\Trigger;


use Alaress\Scheduler\Event\EventInterface;

abstract class AbstractTrigger implements TriggerInterface {

    /** @var string */
	protected $label;

    /** @var EventInterface */
    protected $event;


    /**
     * Return the trigger label that this trigger is based on
     *
     * @return string
     */
    public function getLabel() {
        return $this->label;
    }


    /**
     * Return the event that precipitated this trigger
     *
     * @return EventInterface
     */
    public function getEvent() {
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

}