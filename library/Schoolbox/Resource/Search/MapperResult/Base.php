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
     * Search results
     *
     * @var \Schoolbox\Collection
     */
    protected $result = null;

    /**
     * Total count of items without limits
     *
     * @var int
     */
    protected $count = 0;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setResult(new Collection());
    }

    /**
     * Execute search
     * Execute search count
     *
     * @param User $user
     * @param Mapper $mapper
     * @param $keyword
     * @param $sortProperty
     * @param $sortDirection
     * @param $offset
     * @param $limit
     */
    public function search(User $user, Mapper $mapper, $keyword, $sortProperty, $sortDirection, $offset, $limit) {

        /* Sanitise inputs
         */
        $sanitiser = new Sanitiser($mapper, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        /* Collect search results
         */
        $this->setResult($this->executeSearch($user, $mapper, $sanitiser));

        /* Collect search results count without limit/offset params
         */
        $this->setCount($this->executeSearchCount($user, $mapper, $sanitiser));
    }

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

    /* Getters/Setters
     */

    /**
     * Set count
     *
     * @param int $count
     */
    protected function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Get count
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set result
     *
     * @param \Schoolbox\Collection $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return \Schoolbox\Collection
     */
    public function getResult()
    {
        return $this->result;
    }
} 