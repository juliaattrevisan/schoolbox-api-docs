<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage\ModelToArray;

use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User as UserModel;
use Schoolbox\User\Translator\ModelToArray;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 */
class User extends ModelToArray
{
    /**
     * @param UserModel                                 $user
     * @param GenericCollection|UserAccess[]|array|bool $groups
     * @return array
     */
    public function __invoke(UserModel $user, $groups = false): array
    {
        return [
            'id'        => $user->getId(),
            '_links'    => $this->deriveLinks($user),
            'title'     => $user->getTitle(),
            'firstName' => $user->getFirstname(),
            'lastName'  => $user->getLastname(),
            'role'      => $user->getRole()->getName(),
            'isStaff'   => $user->getRole()->isStaff(),
            'campus'    => implode(', ', $user->getCampusCollection()->getPropertyValues('name')),
            'yearLevel' => $user->getYear() ? $user->getYear()->getLongName() : "",
            'house'     => $user->getSchoolHouse() ? $user->getSchoolHouse()->getHouse() : '',
        ];
    }
}
