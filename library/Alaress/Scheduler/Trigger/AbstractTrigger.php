<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Schedule\Trigger;


use Alaress\Schedule\Event\EventInterface;

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

}