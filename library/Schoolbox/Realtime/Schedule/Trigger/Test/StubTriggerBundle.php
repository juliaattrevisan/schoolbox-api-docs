<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;


use Schoolbox\Realtime\Schedule\Event;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;

class StubTriggerBundle implements TriggerBundleInterface {

	/**
	 * @param string $label
	 * @param int    $timestamp
	 */
	public function attach($label, $timestamp) {
		// noop
	}


	/**
	 * @param array $array
	 */
	public function attachEach($array) {
		// noop
	}


	/**
	 * @param string $label
	 */
	public function detach($label) {
		// noop
	}


	/**
	 * @param array $array
	 */
	public function detachEach($array) {
		// noop
	}


	/**
	 * @return string
	 */
	public function getMethod() {
		// noop
	}


	/**
	 * @return Event
	 */
	public function getEvent() {
		// noop
	}

} 