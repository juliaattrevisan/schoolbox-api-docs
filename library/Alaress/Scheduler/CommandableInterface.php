<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


interface CommandableInterface {

	/**
	 * Bind the queue(s) relevant to your commands
	 *
	 * @return void
	 */
	public static function bindQueues();

} 