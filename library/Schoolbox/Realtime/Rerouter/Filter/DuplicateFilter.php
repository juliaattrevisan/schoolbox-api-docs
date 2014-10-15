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
use Schoolbox\Notif\UserQueue\UserQueueFactory;
use Schoolbox\User;

class DuplicateFilter implements FilterInterface {

	/** @var UserQueueFactory */
	protected $uqf;


	/**
	 * @param UserQueueFactory $uqFactory
	 */
	public function __construct($uqFactory) {
		$this->uqf = $uqFactory;
	}


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
		if($this->uqf->buildForUser($user->getId())->contains($message)) {
			throw new FilterException("User has already seen this message");
		}
		return true;
	}

} 