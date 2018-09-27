<?php
/**
 * ValidUserIsCommsOfficer
 *
 * Definition of a $user who has the Comms Officer service permission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

class ValidUserIsCommsOfficer
{

    /**
     * Definition of a $user who has the Comms Officer service permission
     *
     * @param User $user
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        return UserAccessMapper::getInstance()
            ->findUsersForService(UserAccess::SERVICE_NEWS)
            ->getUnique('id')
            ->indexBy('id')
            ->offsetExists($user->getId());
    }
}
