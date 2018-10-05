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
     * @param array                 $orderBy
     * @param null|int              $limit
     * @return int[]
     */
    public function findArticlesForAudience(string $entityType, $entityValue, ?string $relationship, array $orderBy = [], int $limit = null): array;
}
