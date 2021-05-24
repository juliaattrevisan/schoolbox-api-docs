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

class CampusMembershipFilter extends ContextFilter
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
        // if campus set, must be involved in one or more campuses listed
        if (isset($message->getContext()['campus'])) {
            $this->assertContextValue($message->getContext()['campus'], $user->getCampusCollection()->getColumnValues('id'), "User not a member of any given campus");
        }
        return true;
    }
}
