<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\Realtime\MessageList\IListFactory;
use Schoolbox\User;

class DuplicateFilter implements FilterInterface
{

    /** @var IListFactory */
    protected $factory;


    /**
     * @param IListFactory $factory
     */
    public function __construct(IListFactory $factory)
    {
        $this->factory = $factory;
    }


    /**
     * Throws a FilterException if this message should be DROPPED for this user
     *
     * @param Instance $message
     * @param User     $user
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
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
