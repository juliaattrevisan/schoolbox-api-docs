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

abstract class OnetimeTrigger extends AbstractTrigger {

	protected $timestamp;

    /**
     * @param string $what - the label describing this trigger
     * @param int $when    - the timestamp at which this trigger should fire
     * @param EventInterface $why   - the event that precipitated this trigger's existence
     */
    public function __construct($what, $when, $why) {
        $this->label = $what;
        $this->timestamp = $when;
        $this->event = $why;
    }

	/**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

//	/**
//	 * Return an array of callables that are appropriate for this trigger
//	 *
//	 * @return callable[]
//	 */
//	public function getCallbacks() {
//		return $this->notifier->retrieveCallbacks($this->label);
//	}

}