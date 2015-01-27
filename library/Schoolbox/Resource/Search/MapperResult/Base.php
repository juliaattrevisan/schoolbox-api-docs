<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Search\MapperResult;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;

abstract class Base {

    /**
     * Get all the search results
     *
     * @param User $user
     * @param Mapper $mapper
     * @param Sanitiser $sanitiser
     * @return Collection
     */
    abstract protected function executeSearch(User $user, Mapper $mapper, Sanitiser $sanitiser);

    /**
     * The total number of items found by the search
     *
     * @param User $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser $sanitiser
     * @return int
     */
    abstract protected function executeSearchCount(User $user, Mapper $mapper, Sanitiser $sanitiser);
} 