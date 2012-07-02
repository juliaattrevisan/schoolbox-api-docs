<?php
/**
 * Calendar_Bridge_Factory
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Bridge_Factory {

	public function produce() {
		switch(Cfg::get('site_domain')) {
			case 'stk':
				return Calendar_Bridge_Stk();

			case 'jpc':
				return Calendar_Bridge_JPC();

			default:
				return new Calendar();
		}
	}
}
