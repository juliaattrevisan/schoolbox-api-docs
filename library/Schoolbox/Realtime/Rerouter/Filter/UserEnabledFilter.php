<?php
/**
 * Reroute filter - stops disabled users from receiving notifications at all.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Rerouter\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\User;

class UserEnabledFilter extends ContextFilter
{

    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param array    $formatted
     * @param User     $user
     *
     * @throws FilterException
     * @return bool
     */
    public function validate($message, $formatted, $user)
    {
        if (!$user->getEnabled()) {
            throw new FilterException("Disabled users do not get notifications");
        }
        return true;
    }
}
