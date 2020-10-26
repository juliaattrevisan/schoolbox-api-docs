<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator\Author;

use Schoolbox\User;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * This variant translates only the subset of fields that are relevant for
 * conveying that the user is the author of content.
 */
class ModelToArray
{
    /**
     * @param User $user
     * @return array
     */
    public function __invoke(User $user): array
    {
        return [
            'id'        => $user->getId(),
            '_links'    => $this->deriveLinks($user),
            'title'     => $user->getTitle(),
            'firstName' => $user->getFirstname(),
            'lastName'  => $user->getLastname(),
            'fullName'  => $user->getFullName(),
        ];
    }


    /**
     * Derive links for this user.
     *
     * @param User $user
     * @return string[]
     */
    private function deriveLinks(User $user): array
    {
        return $user->getId()
            ? [
                'profile' => '/search/user/' . $user->getId(),
                'avatar'  => '/portrait.php?id=' . $user->getId(),
            ]
            : [];
    }
}
