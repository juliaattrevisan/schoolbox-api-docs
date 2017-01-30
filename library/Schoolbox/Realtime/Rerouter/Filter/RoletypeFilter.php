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

class RoletypeFilter extends ContextFilter {

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
		// if roletype set, must be of role specified
		if(isset($formatted['context']['roletype'])) {
			if(!$this->testContextValue($formatted['context']['roletype'], $user->getRole()->getType())) {
				throw new FilterException(
				    "User not a member of any given roletype; got "
                    . json_encode($user->getRole()->getType())
                    . ", expected one of "
                    . json_encode($formatted['context']['roletype'])
                );
			}
		}
		return true;
	}

}
