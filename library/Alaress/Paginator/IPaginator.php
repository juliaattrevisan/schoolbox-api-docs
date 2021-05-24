<?php

/**
 * Interface for paginator helpers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Paginator;

use OutOfRangeException;

interface IPaginator
{

    /**
     * Publicly set properties of paginators: items displayed per page, and
     * total items displayed
     */

    /**
     * Gets the number of items displayed per page.
     *
     * @return int
     */
    public function getPageSize();

    /**
     * Sets the number of items displayed per page.
     *
     * @param  int   $pageSize
     * @return $this
     */
    public function setPageSize($pageSize);

    /**
     * Sets the total number of items to paginate.
     *
     * @param  int   $total
     * @return $this
     */
    public function setTotal($total);


    /**
     * Properties derived from the already set page size and total
     */

    /**
     * Gets the number of pages.
     *
     * @return int
     */
    public function getPageCount();


    /**
     * Properties derived from the page size, total and a passed page number
     */

    /**
     * Gets the 0-indexed offset of the first item on page $pageNumber.
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getOffset($pageNumber);

    /**
     * Gets the index of the first item on page $pageNumber.
     *
     * This is 1-indexed, so it isn't really suitable for DB queries - try
     * getOffset($pageNumber) for a 0-indexed offset.
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getFirstOnPage($pageNumber);

    /**
     * Gets the index of the last item on page $pageNumber.
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getLastOnPage($pageNumber);
}
