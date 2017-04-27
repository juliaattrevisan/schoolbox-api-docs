<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Schoolbox\Realtime\Schedule\Event\Event;

interface TriggerBundleInterface {

	/**
	 * @param string $label
	 * @param int $timestamp
	 */
	public function attach($label, $timestamp);


	/**
	 * @param array $array
	 */
	public function attachEach($array);


	/**
	 * @param string $label
	 */
	public function detach($label);


	/**
	 * @param array $array
	 */
	public function detachEach($array);


	/**
	 * @return string
	 */
	public function getMethod();

	/**
	 * @return Event
	 */
	public function getEvent();

} 