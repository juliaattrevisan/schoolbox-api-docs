<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper;

use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Interface for Mapper objects which may be used as the source for a tag cloud.
 *
 * A stripped-down version of Searchable
 *
 * @package Schoolbox\Mapper
 */
interface UsableAsTagSource
{
    const DEFAULT_LIMIT = 20;

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User   $user    The user performing the search.
     *                        (Some results aren't viewable by all users)
     * @param string $keyword Search string
     * @param int    $offset  Number of results to offset result by
     * @param int    $limit   Max number of results to retrieve
     * @return Collection
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT);
}
