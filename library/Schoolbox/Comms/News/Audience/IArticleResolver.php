<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\User;

interface IArticleResolver
{

    /**
     * Return the set of ids of articles whose audience contains this user
     *
     * @param User     $user
     * @param int|null $state If provided, find only IDs of articles in this state
     * @param bool     $savedOnly If true, returns only saved articles
     * @return int[]
     */
    public function findArticlesForUser(User $user, ?int $state, bool $savedOnly = false): array;


    /**
     * Returns the ids of the set of articles that were targeted to this audience.
     *
     * Examples: articleA.audience = [folder:X ∪ role:Y]     // users in folderX OR roleY
     *           articleB.audience = [folder:X ∩ role:Y]     // users in folderX AND roleY
     *
     *           findArticlesForAudience([folder:X])
     *              should include articleA                  // as [folder:X] is a subset of [folder:X ∪ role:Y]
     *              should NOT include articleB              // as [folder:X] is not a subset of [folder:X ∩ role:Y]. The difference being [!folder:X ∩ role:Y]
     *
     *           findArticlesForAudience([folder:X ∪ role:Y])
     *              should include articleA                  // as [folder:X ∪ role:Y] is a subset of [folder:X ∪ role:Y]
     *              should NOT include articleB              // as [folder:X ∪ role:Y] is not a subset of [folder:X ∩ role:Y]
     *
     *           findArticlesForAudience([folder:X ∩ role:Y])
     *              should include articleA                  // as [folder:X ∩ role:Y] is a subset of [folder:X ∪ role:Y]
     *              should include articleB                  // as [folder:X ∩ role:Y] is a subset of [folder:X ∩ role:Y]
     *
     * @param string                $entityType
     * @param int|string|null|array $entityValue  Null is a valid value to match on. Arrays are used in complex searches
     * @param string|null           $relationship Null means don't don't filter by relationship modifier.
     * @param bool                  $publishedOnly Only get published (not awaiting publication or expired) articles
     * @param array                 $orderBy
     * @param null|int              $limit
     * @return int[]
     */
    public function findArticlesForAudience(string $entityType, $entityValue, ?string $relationship, bool $publishedOnly = true, array $orderBy = [], int $limit = null): array;
}
