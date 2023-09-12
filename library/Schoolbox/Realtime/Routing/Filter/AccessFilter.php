<?php

/**
 *
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

class AccessFilter extends ContextFilter
{
    public function __construct(private Access $access)
    {
    }

    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param User     $user
     * @return bool
     * @throws FilterException
     */
    public function validate(Instance $message, User $user): bool
    {
        // if access is set, the user must have access to it
        if (isset($message->getContext()['access'])) {
            try {
                $accessLevel = $this->access->getFolderAccess($user, (int)$message->getContext()['access']);
            } catch (MapperException $e) {
                throw new FilterException($e->getMessage());
            }
            $this->assertContextValue(
                [
                    UserAccess::ACCESS_READ,
                    UserAccess::ACCESS_WRITE,
                    UserAccess::ACCESS_FULL,
                ],
                $accessLevel,
                "User does not have access"
            );
        }
        return true;
    }
}
