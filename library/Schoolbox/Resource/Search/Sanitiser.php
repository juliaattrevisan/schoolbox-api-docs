<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search;

use Schoolbox\Mapper;

/**
 * Class Sanitiser
 *
 *
 * Santises search parameters Keyword, Sort Property, Sort Direction, Offset, Limit
 *
 * - Keyword is quoted
 *
 * - Sort Property is based on mapper's defined properties.
 * If it's not in the mapper's defined properties, will sort by identifier column
 *
 * - Sort direction will always return DESC or ASC.
 *
 * - Also casts (int) on offset and limit.
 *
 * @package Schoolbox\Resource\Search
 */
class Sanitiser
{
    /**
     * Alias for the name to search by.
     */
    const SCORE_COLUMN = 'score';


    /**
     * Sort by this column name
     *
     * @var string
     */
    private $sortBy = '';


    /**
     * Sort direction
     *
     * @var string
     */
    private $sortDir = 'DESC';


    /**
     * Offset
     *
     * @var int
     */
    private $offset = 0;


    /**
     * Page Limit
     *
     * @var int
     */
    private $pageLimit = 0;


    /**
     * Max results limit
     * @var int
     */
    private $resultLimit = 500;


    /**
     * Keyword
     *
     * @var string
     */
    private $keyword = '';


    /**
     * Keyword (unquoted)
     *
     * @var string
     */
    private $unquotedKeyword = '';


    /**
     * Is this sort on an actual table column?
     *
     * @var bool
     */
    private $sortOnTableColumn = false;


    /**
     * Sanitise search params
     *
     * Immutable
     *
     * @param \Schoolbox\Mapper $mapper
     * @param string $keyword
     * @param string $sortProperty
     * @param string $sortDirection
     * @param int $offset
     * @param int $limit
     */
    public function __construct(Mapper $mapper, $keyword, $sortProperty, $sortDirection, $offset, $limit)
    {
        /* Check whether the field you're sorting on exists
         * in mapping definition before trying to sort on it
         */
        if (strlen($sortProperty) && $mapper->getMap()->offsetExists($sortProperty)) {
            $mappingDefinition = $mapper->getMap()->getOffset($sortProperty);
            $this->setSortBy($mappingDefinition['column']);
            $this->setSortOnTableColumn(true);
        } elseif ($sortProperty == self::SCORE_COLUMN) {
            // self::SCORE_COLUMN is a column in the search query, not in any table
            $this->setSortBy(self::SCORE_COLUMN);
            $this->setSortOnTableColumn(false);
        } else {
            $this->setSortBy($mapper->getIdentifierColumn());
            $this->setSortOnTableColumn(true);
        }

        /* Sort direction
         */
        $this->setSortDir(strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC');

        /* Limits and offsets
         */
        $this->setOffset((int)$offset);
        $this->setPageLimit((int)$limit);

        /* PDO
         */
        $this->setKeyword($mapper->getDatabase()->quote($keyword));
        $this->setUnquotedKeyword($keyword);
    }

    /* Getters/Setters
     */

    /**
     * Set page limit
     *
     * @param int $limit
     */
    private function setPageLimit($limit)
    {
        $this->pageLimit = $limit;
    }

    /**
     * Get page limit
     *
     * @return int
     */
    public function getPageLimit()
    {
        return $this->pageLimit;
    }

    /**
     * Get result limit
     * @return int
     */
    public function getResultLimit(): int
    {
        return $this->resultLimit;
    }

    /**
     * Set result limit
     * @param int $resultLimit
     */
    public function setResultLimit($resultLimit): void
    {
        $this->resultLimit = $resultLimit;
    }

    /**
     * Set offset
     *
     * @param int $offset
     */
    private function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * Get offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set sort by
     *
     * @param string $sortBy
     */
    private function setSortBy($sortBy)
    {
        $this->sortBy = $sortBy;
    }

    /**
     * Get sort by
     *
     * @return string
     */
    public function getSortBy()
    {
        return $this->sortBy;
    }

    /**
     * Gets sort by, with alias if applicable.
     *
     * Alias is applicable if the sort is on an actual table column, not on a
     * column in a result set.
     *
     * @param  string $alias
     * @return string
     */
    public function getSortByWithAlias($alias)
    {
        $sortBy = $this->getSortBy();
        if ($this->getSortOnTableColumn()) {
            return $alias . '.' . $sortBy;
        } else {
            return $sortBy;
        }
    }

    /**
     * Set sort dir
     *
     * @param string $sortDir
     */
    private function setSortDir($sortDir)
    {
        $this->sortDir = $sortDir;
    }

    /**
     * Get sort dir
     *
     * @return string
     */
    public function getSortDir()
    {
        return $this->sortDir;
    }

    /**
     * Set keyword
     *
     * @param string $keyword
     */
    private function setKeyword($keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * Get keyword
     *
     * @return string
     */
    public function getKeyword()
    {
        return $this->keyword;
    }

    /**
     * @return string
     */
    public function getUnquotedKeyword()
    {
        return $this->unquotedKeyword;
    }

    /**
     * @param  string $unquotedKeyword
     *
     * @return $this
     */
    private function setUnquotedKeyword($unquotedKeyword)
    {
        $this->unquotedKeyword = $unquotedKeyword;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSortOnTableColumn()
    {
        return $this->sortOnTableColumn;
    }

    /**
     * @param  bool  $sortOnTableColumn
     * @return $this
     */
    private function setSortOnTableColumn($sortOnTableColumn)
    {
        $this->sortOnTableColumn = $sortOnTableColumn;
        return $this;
    }
}
