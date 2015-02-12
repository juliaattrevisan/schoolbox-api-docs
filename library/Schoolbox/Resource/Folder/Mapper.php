<?php
/**
 * Mapper
 *
 * Eventually migrate CategoryMapper into here
 * 
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;

use Schoolbox\Resource\Folder\Search\Result;
use Schoolbox\Resource\CategoryMapper;

class Mapper extends CategoryMapper {

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Collection
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }
} 