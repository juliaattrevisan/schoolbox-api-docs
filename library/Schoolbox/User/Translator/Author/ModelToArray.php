<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator\Author;

use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * This variant translates only the subset of fields that are relevant for
 * conveying that the user is the author of content.
 *
 * The information returned by this translator is guaranteed to be fit for all
 * audiences: it contains only publicly available information such as:
 * * the user's ID
 * * any names
 * * links to the profile page and profile image
 */
class ModelToArray extends User\Translator\ModelToArray
{
    /**
     * @param User                                      $user
     * @param GenericCollection|UserAccess[]|array|bool $groups
     * @return array
     */
    public function __invoke(User $user, $groups = false): array
    {
        return [
            'id'            => $user->getId(),
            '_links'        => $this->deriveLinks($user),
            'title'         => $user->getTitle(),
            'firstName'     => $user->getFirstname(),
            'preferredName' => $user->getPreferredName(),
            'lastName'      => $user->getLastname(),
            'fullName'      => $user->getFullName(),
            'isDeleted'     => $user->isDeleted(),
        ];
    }
}
