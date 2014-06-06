<?php
/**
 * Implementation of a simple paginator.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Paginator;

use OutOfRangeException;

class Paginator implements IPaginator {

    /**
     * @var int
     */
    protected $pageSize;

    /**
     * @var int
     */
    protected $total;

//    /**
//     * @param int $pageSize Number of items per page
//     * @param int $total    Total number of items
//     */
//    public function __construct($pageSize, $total) {
//        $this->setPageSize($pageSize)->setTotal($total);
//    }

//    /**
//     * Gets the offset
//     *
//     * @param  int $pageNumber
//     * @return int
//     */
//    public function getOffset($pageNumber) {
//        return ($pageNumber - 1) * $this->pageSize;
//    }
//
//    /**
//     * Gets the limit
//     *
//     * @param  int $pageNumber
//     * @return int
//     */
//    public function getLimit($pageNumber) {
//        return $this->pageSize;
//    }

    /**
     * Ensures that $pageNumber is between the first and last pages.
     *
     * @param  int $pageNumber
     * @throws OutOfRangeException if not $this->getFirstPage() > $pageNumber > $this->getLastPage()
     */
    protected function assertPageNumberWithinRange($pageNumber) {
        if ($pageNumber < ($first = $this->getFirstPage())) {
            throw new OutOfRangeException(
                sprintf('Requested page number "%d" less than first page "%d"', $pageNumber, $first)
            );
        }
        if ($pageNumber > ($last = $this->getLastPage())) {
            throw new OutOfRangeException(
                sprintf('Requested page number "%d" greater than last page "%d"', $pageNumber, $last)
            );
        }
    }

    /**
     * Gets the 0-indexed offset of the first item on page $pageNumber.
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getOffset($pageNumber) {
        $this->assertPageNumberWithinRange($pageNumber);
        return (($pageNumber - 1) * $this->pageSize);
    }

    /**
     * Gets the first item on the page
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getFirstOnPage($pageNumber) {
        return $this->getOffset($pageNumber) + 1;
    }

    /**
     * Gets the last item on the page
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getLastOnPage($pageNumber) {
        $this->assertPageNumberWithinRange($pageNumber);
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
        return min(1, $this->getLastPage());
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
     * @return int
     */
    public function getPageSize() {
        return $this->pageSize;
    }

    /**
     * @param  int   $pageSize
     * @return $this
     * @throws OutOfRangeException if $pageSize <= 0
     */
    public function setPageSize($pageSize) {
        $pageSize = intval($pageSize);
        if ($pageSize <= 0) {
            throw new OutOfRangeException('Page size must be at least 1');
        }
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * @param  int   $total
     * @return $this
     * @throws OutOfRangeException if $total <= 0
     */
    public function setTotal($total) {
        $total = intval($total);
        if ($total < 0) {
            throw new OutOfRangeException('Total must be at least 0');
        }
        $this->total = $total;
        return $this;
    }

}
