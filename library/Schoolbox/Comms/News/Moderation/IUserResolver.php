<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

interface IUserResolver
{

    /**
     * Returns the ids of users who are capable of moderating this article
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @return int[]
     */
    public function findModeratorsForArticle(\Schoolbox\Comms\News\Model $article): array;

}
