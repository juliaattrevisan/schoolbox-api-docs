<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\User;

class RoleMembershipFilter extends ContextFilter
{

    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param User     $user
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
    {

        // if roletype set, must be of role specified
        if (isset($message->getContext()['role'])) {
            $this->assertContextValue($message->getContext()['role'], $user->getRole()->getId(), "User not a member of any given role");
        }
        return true;
    }
}
