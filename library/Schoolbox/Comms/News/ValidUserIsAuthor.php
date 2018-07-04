<?php
/**
 * ValidUserIsAuthor
 *
 * Definition of a $user who is considered an author of $news
 * There's an adminv2/policy that allows staff to edit each other's stuff
 * There's also homepage-level moderation where you might want multiple people considered as "authors"
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class ValidUserIsAuthor
{

    /**
     * Definition of a $user who is considered an author of $news
     *
     * @param User  $user
     * @param Model $news
     * @return bool
     */
    public function isSatisfiedBy(User $user, Model $news): bool
    {
        return $user->getId() === $news->getAuthor()->getId();
    }
}
