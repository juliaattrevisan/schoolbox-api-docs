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


/**
 * A specific type of trigger to unset a previously-scheduled event. Implements timestamp and callbacks as empty.
 *
 * Class CancelTrigger
 * @package Schoolbox\Realtime\Schedule\Trigger
 */
class CancelTrigger extends AbstractTrigger {


    /**
     * @param string $label
     * @param EventInterface $event
     */
    function __construct($label, $event) {
        $this->label = $label;
        $this->event = $event;
    }


    /**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return null;
	}


	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return array();
	}

} 