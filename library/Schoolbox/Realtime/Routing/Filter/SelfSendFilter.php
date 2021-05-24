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

class SelfSendFilter implements FilterInterface
{

    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param User $user
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
    {
        // if we do not allow self send
        if (!(isset($message->getContext()['allowSelfSend']) && $message->getContext()['allowSelfSend'] === true)) {
            // if the originating user matches current, dismiss
            if (isset($message->getContext()['user']) && $message->getContext()['user'] == $user->getId()) {
                throw new FilterException("User effected this message");
            }
        }
        return true;
    }
}
