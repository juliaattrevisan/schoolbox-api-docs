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
use Schoolbox\Realtime\MessageList\IFactory;
use Schoolbox\User;

class DuplicateFilter implements FilterInterface
{

	/** @var IFactory */
	protected $factory;


	/**
	 * @param IFactory $factory
	 */
	public function __construct(IFactory $factory)
    {
		$this->factory = $factory;
	}


	/**
	 * Throws a FilterException if this message should be DROPPED for this user
	 *
	 * @param Instance $message
	 * @param array    $formatted
	 * @param User     $user
	 * @throws FilterException
	 * @return bool
	 */
	public function validate($message, $formatted, $user)
    {
        if ($this->factory->listed($user->getId())->contains($message)) {
            throw new FilterException('User has already seen this message');
        }

        if ($this->factory->digest($user->getId())->contains($message)) {
            throw new FilterException("This message is already scheduled into this user's digest");
        }

        return true;
    }

}
