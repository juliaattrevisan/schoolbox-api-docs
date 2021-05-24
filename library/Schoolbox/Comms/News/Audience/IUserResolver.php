<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Comms\News;
use Schoolbox\User;

interface IUserResolver
{

    /**
     * Returns the ids of users who are in this article's audience
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @param bool                        $excludeMuted
     * @return int[]
     */
    public function findUsersForArticle(\Schoolbox\Comms\News\Model $article, bool $excludeMuted = false): array;


    /**
     * Returns whether the specified user is in the article's audience.
     *
     * @param User       $user
     * @param News\Model $article
     * @return bool
     */
    public function isUserInAudience(User $user, News\Model $article): bool;
}
