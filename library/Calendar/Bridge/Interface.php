<?php
/**
 * Calendar_Bridge_Interface
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
interface Calendar_Bridge_Interface {
	public function getAllEvents();
	public function getAllEventsForCategory();
}
