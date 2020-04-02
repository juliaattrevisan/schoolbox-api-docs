<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

interface IUserResolver
{

    /**
     * Returns the ids of users who are capable of moderating this article
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @param bool $includeFolderWriteUsers
     * @return int[]
     */
    public function findModeratorsForArticle(\Schoolbox\Comms\News\Model $article, bool $includeFolderWriteUsers = true): array;


    /**
     * Returns the user ids of all news moderators (users with FULL access to NEWS service)
     *
     * @return int[]
     */
    public function getGlobalNewsModerators(): array;
}
