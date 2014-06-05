<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Realtime\Notification\Parameter;


use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\User;

class UserParam extends Param {

	/**
	 * Use standard vars from the user object, and default to the append stacking mode
	 *
	 * @param User $user
	 * @param int $stacking
	 */
	public function __construct($user, $stacking = self::STACKING_APPEND) {
		parent::__construct($user->formatName(), "/search/user/{$user->getId()}", $stacking);
	}


} 