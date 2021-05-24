<?php

/**
 * Implementation of a simple paginator.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Paginator;

use OutOfRangeException;

class Paginator implements IPaginator
{

    /**
     * @var int
     */
    protected $pageSize;

    /**
     * @var int
     */
    protected $total;

    /**
     * Ensures that $pageNumber is between the first and last pages.
     *
     * @param  int $pageNumber
     * @throws OutOfRangeException if not $this->getFirstPage() > $pageNumber > $this->getLastPage()
     */
    protected function assertPageNumberWithinRange($pageNumber)
    {
        $pageCount = $this->getPageCount();
        $firstPage = min($pageCount, 1);
        if ($pageNumber < $firstPage) {
            throw new OutOfRangeException(
                sprintf('Requested page number "%d" less than first page "%d"', $pageNumber, $firstPage)
            );
        }
        if ($pageNumber > $pageCount) {
            throw new OutOfRangeException(
                sprintf('Requested page number "%d" greater than last page "%d"', $pageNumber, $pageCount)
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
    public function getOffset($pageNumber)
    {
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
    public function getFirstOnPage($pageNumber)
    {
        return $this->getOffset($pageNumber) + 1;
    }

    /**
     * Gets the last item on the page
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getLastOnPage($pageNumber)
    {
        $this->assertPageNumberWithinRange($pageNumber);
        return min($this->total, $pageNumber * $this->pageSize);
    }

    /**
     * Gets the number of pages.
     *
     * @return int
     */
    public function getPageCount()
    {
        return intval(ceil($this->total / $this->pageSize));
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * @param  int   $pageSize
     * @return $this
     * @throws OutOfRangeException if $pageSize <= 0
     */
    public function setPageSize($pageSize)
    {
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
    public function setTotal($total)
    {
        $total = intval($total);
        if ($total < 0) {
            throw new OutOfRangeException('Total must be at least 0');
        }
        $this->total = $total;
        return $this;
    }
}
