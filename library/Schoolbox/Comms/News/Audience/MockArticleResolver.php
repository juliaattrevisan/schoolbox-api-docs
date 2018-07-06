<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\User;

class MockArticleResolver implements IArticleResolver
{

    /**
     * @inheritdoc
     */
    public function findArticlesForUser(User $user): array
    {
        $articles = [];
        foreach (MockUserResolver::ARTICLE_AUDIENCES as $articleId => $userIds) {
            if (\in_array($user->getId(), $userIds, true)) {
                $articles[] = $articleId;
            }
        }
        return $articles;
    }
}
