<?php
/**
 * Index
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Schoolbox\User;

class Index extends Base
{
    /**
     * If the user is a superuser or a news moderator
     *
     * Article must be published
     * and article must not be expired
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you approved the article
     *    - you're in the audience
     *
     * Everyone else
     *
     * Article must be published
     * and article must not be expired
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you're in the audience
     *
     * @param User        $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     */
    public function find(User $user): array  {
        return array_unique(
            array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user)
            )
        );
    }
}
