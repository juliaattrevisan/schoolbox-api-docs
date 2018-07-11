<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\User;

interface IArticleResolver
{

    /**
     * Return the set of ids of articles whose audience contains this user
     *
     * @param User $user
     * @return int[]
     */
    public function findArticlesForUser(User $user): array;
}
