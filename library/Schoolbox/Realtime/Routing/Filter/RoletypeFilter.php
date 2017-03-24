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

class RoletypeFilter extends ContextFilter {

	/**
	 * Returns true if this message is considered valid, or false if it should be dismissed
	 *
	 * @param Instance $message
	 * @param User     $user
	 * @throws FilterException
	 * @return bool
	 */
	public function validate(Instance $message, User $user) {
		// if roletype set, must be of role specified
		if(isset($message->getContext()['roletype'])) {
			$this->assertContextValue($message->getContext()['roletype'], $user->getRole()->getType(), "User not a member of any given roletype");
		}
		return true;
	}

}
