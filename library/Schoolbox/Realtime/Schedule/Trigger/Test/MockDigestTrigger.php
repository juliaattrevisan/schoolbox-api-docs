<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;


use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;

class MockDigestTrigger extends DigestTrigger {

	protected $time;

	/**
	 * OVERRIDE: always returns 50 seconds from now
	 * @return int
	 */
	public function getTimestamp() {
		if(!isset($this->time)) {
			// first iteration fires immediately
			$this->time = time() + 600;
			return time();
		}
		// every 5 minutes thereafter
		$this->time = time() + 600;
		return $this->time;
	}

} 