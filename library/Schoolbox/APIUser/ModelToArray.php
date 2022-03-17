<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * The information returned by this translator may contain private information,
 * such as the user's email address:
 * see `\Schoolbox\User\Translator\Author\ModelToArray` for a variant which is
 * guaranteed to return only public information.
 */
class ModelToArray extends User\Translator\ModelToArray
{
    /**
     * @param User            $user
     * @param Collection|null $groups
     * @param Collection|null $students
     * @return array
     */
    public function __invoke(User $user, Collection $groups = null, Collection $students = null): array
    {
        $tmp = parent::__invoke($user);
        $user->loadParents();
        $tmp['guardians'] = $user->getParents();
        $tmp['students'] = $students;
        $tmp['groups'] = $groups;
        $tmp['impersonableUser'] = $user->getLinkedUser();
        return $tmp;
    }
}
