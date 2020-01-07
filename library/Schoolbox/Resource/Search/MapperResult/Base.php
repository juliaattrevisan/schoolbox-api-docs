<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search\MapperResult;

use CallbackFilterIterator;
use IteratorIterator;
use LimitIterator;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Traversable;

abstract class Base
{

    /**
     * Search results
     *
     * @var \Schoolbox\Collection
     */
    protected $result;

    /**
     * Total count of items without limits
     *
     * @var int
     */
    protected $count = 0;

    /**
     * The object that is used to determine whether a user has access to a resource.
     *
     * @var Access
     */
    protected $accessor;

    /**
     * Constructor
     *
     * @param Access $accessor
     */
    public function __construct(Access $accessor = null)
    {
        $this->setResult(new Collection());
        // TODO make $accessor injection mandatory
        $this->setAccessor(($accessor instanceof Access) ? $accessor : Access::getInstance());
    }


    /**
     * Execute search
     * Execute search count
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param                 $keyword
     * @param                 $sortProperty
     * @param                 $sortDirection
     * @param                 $offset
     * @param                 $limit
     */
    public function search(ArticleResolver $articleResolver, User $user, Mapper $mapper, $keyword, $sortProperty, $sortDirection, $offset, $limit)
    {

        /* Sanitise inputs
         */
        $sanitiser = new Sanitiser($mapper, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        /* Collect search results
         */
        $this->setResult($this->executeSearch($articleResolver, $user, $mapper, $sanitiser));

        /* You can save a db request by using the $setResult() outcome and
         * traversing to $currentPage-1 manually in php, manually constructing
         * the page's contents
         */

        /* Collect search results count without limit/offset params
         */
        $this->setCount($this->executeSearchCount($articleResolver, $user, $mapper, $sanitiser));
    }


    /**
     * Get all the search results
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return Collection
     */
    abstract protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser);


    /**
     * The total number of items found by the search
     *
     * @param ArticleResolver   $articleResolver
     * @param User              $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser         $sanitiser
     * @return int
     */
    abstract protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser);

    /**
     * Builds a paged result set, filtered by whether $user can view it.
     *
     * @param Traversable $rawResult    The raw result set
     * @param User        $user         The user that is viewing the result
     * @param string      $folderColumn The column in $rawResult rows which contains the folder ID
     * @param int         $offset       The number of results to offset by
     * @param int         $limit        The max number of results to retrieve
     * @return LimitIterator
     */
    protected function buildSearchResult(Traversable $rawResult, User $user, $folderColumn, $offset, $limit)
    {
        return new LimitIterator($this->buildFilteredResult($rawResult, $user, $folderColumn), $offset, $limit);
    }

    /**
     * Determines the total number of results in a result set that are viewable by $user.
     *
     * @param Traversable $rawResult    The raw result set
     * @param User        $user         The user that is viewing the result
     * @param string      $folderColumn The column in $rawResult rows which contains the folder ID
     * @return int
     */
    protected function buildSearchCount(Traversable $rawResult, User $user, $folderColumn)
    {
        $count = 0;
        // Sadly CallbackFilterIterator isn't countable, so count manually
        foreach ($this->buildFilteredResult($rawResult, $user, $folderColumn) as $blah) {
            $count++;
        }
        return $count;
    }

    /**
     * Builds a filtered result set, which returns only results viewable by a user.
     *
     * @param Traversable $rawResult    The raw result set
     * @param User        $user         The user that is viewing the result
     * @param string      $folderColumn The column in $rawResult rows which contains the folder ID
     * @return CallbackFilterIterator
     */
    protected function buildFilteredResult(Traversable $rawResult, User $user, $folderColumn)
    {
        return new CallbackFilterIterator(
            new IteratorIterator($rawResult),
            function ($row) use ($user, $folderColumn) {
                return $this->getAccessor()->getFolderAccess($user, (int)$row[$folderColumn]) > UserAccess::ACCESS_DENY;
            }
        );
    }

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

    /**
     * @return Access
     */
    public function getAccessor()
    {
        return $this->accessor;
    }

    /**
     * @param Access $accessor
     * @return static
     */
    public function setAccessor($accessor)
    {
        $this->accessor = $accessor;
        return $this;
    }
}
