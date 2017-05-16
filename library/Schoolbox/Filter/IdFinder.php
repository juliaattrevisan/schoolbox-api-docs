<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\IMapper;

/**
 * An 'IdFinder' is able to take a set of filters, and return a list of IDs of entities that match those filters.
 */
interface IdFinder
{
    /**
     * Fetch the singleton instance of this class
     *
     * @param IMapper $mapper
     * @return IdFinder
     */
    public static function getInstance(IMapper $mapper);

    /**
     * Fetch an array of assessment IDs that match the specified filters
     *
     * Filters are applied using ∩ (intersection).
     *
     * For each filter:
     * * null  => don't apply filter
     * * []    => LEFT JOIN must match NULL. eg if project filter is [] then assessment does not live in project
     * * [X..] => INNER JOIN must be in X.   eg if project filter is [1] then assessments must reside directly in project_1
     *
     * @param Collection[]|null[]|mixed[][] $filters Indexed by filter definition key
     * @param bool                          $deleted Whether to include soft-deleted assessments
     * @see IDefinition::getSlug()
     * @return int[]
     * @throws \InvalidArgumentException If any filter keys not recognised
     * @throws BrokenFilterException If a injected filter is unable to be processed
     */
    public function findIdsByFilters(array $filters = [], $deleted = false);
}
