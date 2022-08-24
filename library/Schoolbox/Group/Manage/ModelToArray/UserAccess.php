<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage\ModelToArray;

use Schoolbox\Permission\Access\UserAccess as UserAccessModel;

/**
 * Translator for translating a userAccess model into an array.
 */
class UserAccess
{
    /**
     * @param UserAccessModel $userAccess
     * @return array
     */
    public function __invoke(UserAccessModel $userAccess): array
    {
        $user = $userAccess->getUser();
        return [
            'id'         => $user->getId(),
            'firstName'  => $user->getFirstname(),
            'lastName'   => $user->getLastname(),
            'userId'     => $user->getId(),
            'accessType' => UserAccessModel::ACCESS_LEVEL_TO_NAME[$userAccess->getAccessLevel()],
            'role'       => $user->getRole()->getName(),
            'campus'     => implode(', ', $user->getCampusCollection()->getPropertyValues('name')),
            'yearLevel'  => ($user->getYear() && $user->getYear()->getName()) ? $user->getYear()->getLongName() : '',
            'house'      => $user->getSchoolHouse() ? $user->getSchoolHouse()->getHouse() : '',
        ];
    }
}
