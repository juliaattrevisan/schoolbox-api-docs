<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\IMapper;
use Schoolbox\User;

/**
 * An 'IdFinder' is able to take a set of filters (indexed by filter definition class),
 * and return a list of IDs of entities that match those filters.
 */
interface IdFinder
{

    /**
     * Fetch the singleton instance of this class
     *
     * @param Mapper  $filterMapper The filter instance mapper
     * @param IMapper $mapper       A mapper for the entity being filtered, eg 'assessments'
     * @return IdFinder
     */
    public static function getInstance(Mapper $filterMapper, IMapper $mapper): self;


    /**
     * Fetch an array of IDs that match the specified filters
     *
     * Filters are applied using ∩ (intersection).
     *
     * For each filter:
     * * null  => don't apply filter
     * * []    => LEFT JOIN must match NULL. eg if project filter is [] then assessment does not live in project
     * * [X..] => INNER JOIN must be in X.   eg if project filter is [1] then assessments must reside directly in project_1
     *
     * @param Collection[]|null[]|mixed[][] $filters Indexed by filter definition class
     * @param User                          $user    The user to use as the context for building filters for
     * @param bool                          $deleted Whether to include soft-deleted entities
     * @return int[]
     * @throws \InvalidArgumentException If any filter keys not recognised
     * @throws BrokenFilterException If a injected filter is unable to be processed
     */
    public function findIdsByFilters(array $filters, User $user, bool $deleted = false): array;
}
