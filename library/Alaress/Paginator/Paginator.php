<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Alaress\Paginator;


class Paginator {

    /**
     * @var int
     */
    protected $pageSize;

    /**
     * @var int
     */
    protected $total;

    /**
     * @param int $pageSize Number of items per page
     * @param int $total    Total number of items
     */
    public function __construct($pageSize, $total) {
        $this->setPageSize($pageSize)->setTotal($total);
    }

    /**
     * Gets the offset
     *
     * @param  int $pageNumber
     * @return int
     */
    public function getOffset($pageNumber) {
        return ($pageNumber - 1) * $this->pageSize;
    }

    /**
     * Gets the limit
     *
     * @param  int $pageNumber
     * @return int
     */
    public function getLimit($pageNumber) {
        return $this->pageSize;
    }

    /**
     * Gets the first item on the page
     *
     * @param  int $pageNumber
     * @return int
     */
    public function getFirstOnPage($pageNumber) {
        return max(1 + ($pageNumber - 1) * $this->pageSize);
    }

    /**
     * Gets the first item on the page
     *
     * @param  int $pageNumber
     * @return int
     */
    public function getLastOnPage($pageNumber) {
        return min($this->total, $pageNumber * $this->pageSize);
    }

    /**
     * Gets the first page number.
     *
     * Usually 1, but will be 0 if $this->total is 0.
     *
     * @return int
     */
    public function getFirstPage() {
        return max(1, $this->getLastPage());
    }

    /**
     * Gets the last page number.
     *
     * @return int
     */
    public function getLastPage() {
        return intval(ceil($this->total / $this->pageSize));
    }

    /**
     * @param  int   $pageSize
     * @return $this
     * @throws \OutOfRangeException if page size <= 0
     */
    public function setPageSize($pageSize) {
        $pageSize = intval($pageSize);
        if ($pageSize <= 0) {
            throw new \OutOfRangeException('Page size must be at least 1');
        }
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * @return int
     */
    public function getPageSize() {
        return $this->pageSize;
    }

    /**
     * @param  int   $total
     * @return $this
     */
    public function setTotal($total) {
        $this->total = $total;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotal() {
        return $this->total;
    }

} 