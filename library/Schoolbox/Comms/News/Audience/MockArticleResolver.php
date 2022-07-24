<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\User;

class MockArticleResolver implements IArticleResolver
{

    /**
     * @inheritdoc
     */
    public function findArticlesForUser(User $user, ?int $state, bool $savedOnly = false): array
    {
        $articles = [];
        foreach (MockUserResolver::ARTICLE_AUDIENCES as $articleId => $userIds) {
            if (\in_array($user->getId(), $userIds, true)) {
                $articles[] = $articleId;
            }
        }
        return $articles;
    }


    /**
     * Returns random, consistent, proportionally sane, mock data
     *
     * @inheritdoc
     */
    public function findArticlesForAudience(
        string $entityType,
        $entityValue,
        ?string $relationship,
        bool $publishedOnly = true,
        array $orderBy = [],
        int $limit = null
    ): array {
        $allArticleIds   = \array_keys(MockUserResolver::ARTICLE_AUDIENCES);
        $countArticleIds = \count($allArticleIds);

        // Hash the type and value into an an array index
        $idx = \abs(\crc32($entityType . $entityValue)) % $countArticleIds;

        // Always return one article
        $articlesForAudience = [$allArticleIds[$idx] => true];

        // And add a few more if no relationship filter
        if (!$relationship) {
            $articlesForAudience[$allArticleIds[\abs(\crc32($entityType . $entityValue . 1)) % $countArticleIds]] = true;
            $articlesForAudience[$allArticleIds[\abs(\crc32($entityType . $entityValue . 2)) % $countArticleIds]] = true;
        }
        // And sort them to simulate a database query
        $articlesForAudience = \array_keys($articlesForAudience);
        \sort($articlesForAudience);
        return $articlesForAudience;
    }
}
