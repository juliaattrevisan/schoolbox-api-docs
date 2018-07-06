<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

interface IUserResolver
{

    /**
     * Returns the ids of users who are in this article's audience
     *
     * @param int $articleId
     * @return int[]
     */
    public function findUsersForArticle(int $articleId): array;
}
