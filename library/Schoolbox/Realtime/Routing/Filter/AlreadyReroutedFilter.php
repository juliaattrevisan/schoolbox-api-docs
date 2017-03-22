<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Routing\Filter;


use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\User;

class AlreadyReroutedFilter implements FilterInterface {

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
		if(isset($formatted['extra']['rerouted'])) {
			throw new FilterException("Message has already been rerouted");
		}
	}

}