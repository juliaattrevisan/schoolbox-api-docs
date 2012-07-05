<?php
/**
 * Calendar_Api_Factory
 *
 * Produce an API class based on school domain. This way schools can
 * have a space to make their own customisations and preprocessing
 * steps before passing data in a standardised format via an instance
 * of Calendar_Event_Collection()
 *
 * Return a default schoolbox API by default, which is just another
 * wrapper around the three calendarHandler methods
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Factory {

	/**
	 * Produce an API class based on school domain
	 *
	 * Return a default schoolbox API by default which is just another
	 * wrapper around the calendarHandler
	 *
	 * @return Calendar_Api_Schoolbox_Default
	 */
	public function produce() {
		switch(Cfg::get('site_domain')) {
//			case 'stk':
//				return Calendar_Bridge_Stk();
//
//			case 'jpc':
//				return Calendar_Bridge_JPC();

			default:
				return new Calendar_Api_Schoolbox_Default();
		}
	}
}
