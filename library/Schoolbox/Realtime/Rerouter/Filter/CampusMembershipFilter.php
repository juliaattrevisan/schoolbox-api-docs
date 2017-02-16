<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter\Filter;


use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\User;

class CampusMembershipFilter extends ContextFilter {

	/**
	 * Returns true if this message is considered valid, or false if it should be dismissed
	 *
	 * @param Instance $message
	 * @param array    $formatted
	 * @param User     $user
	 * @throws FilterException
	 * @return bool
	 */
	public function validate($message, $formatted, $user) {
		// if campus set, must be involved in one or more campuses listed
		if(isset($formatted['context']['campus'])) {
			$this->assertContextValue($formatted['context']['campus'], $user->getCampusCollection()->getColumnValues('id'), "User not a member of any given campus");
		}
		return true;
	}



} 