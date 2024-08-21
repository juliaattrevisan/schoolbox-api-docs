<?php

/**
 * Unit tests for Alaress\Paginator\Paginator class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Paginator\Test;

use Alaress\Paginator\Paginator;
use PHPUnit\Framework\TestCase;
use OutOfRangeException;

class PaginatorTest extends TestCase
{

    /**
     * Simple factory for a test Paginator object.
     *
     * @param  int $pageSize
     * @param  int $total
     * @return Paginator
     */
    protected function buildPaginator($pageSize, $total)
    {
        $paginator = new Paginator();
        $paginator->setPageSize($pageSize)->setTotal($total);
        return $paginator;
    }

    /**
     * @return array
     */
    public function getTestPageCountData()
    {
        return array(
            array($this->buildPaginator(1, 0),  0),
            array($this->buildPaginator(1, 9),  9),

            array($this->buildPaginator(10, 9),  1),
            array($this->buildPaginator(10, 10), 1),
            array($this->buildPaginator(10, 11), 2),

            array($this->buildPaginator(10, 19), 2),
            array($this->buildPaginator(10, 20), 2),
            array($this->buildPaginator(10, 21), 3),

            array($this->buildPaginator(10, 59), 6),
            array($this->buildPaginator(10, 60), 6),
            array($this->buildPaginator(10, 61), 7),

            array($this->buildPaginator(50, 19), 1),
            array($this->buildPaginator(50, 20), 1),
            array($this->buildPaginator(50, 21), 1),
            array($this->buildPaginator(50, 49), 1),
            array($this->buildPaginator(50, 50), 1),
            array($this->buildPaginator(50, 51), 2),
        );
    }

    /**
     * Tests first and last page numbers.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPageCountData')]
    public function testPageCount(Paginator $paginator, $expectedPageCount)
    {
        $this->assertEquals($expectedPageCount, $paginator->getPageCount());
    }

    /**
     * @return array
     */
    public function getTestItemsOnPageData()
    {
        return array(
            array($this->buildPaginator(1, 9),  1, 0, 1, 1),
            array($this->buildPaginator(1, 9),  2, 1, 2, 2),
            array($this->buildPaginator(1, 9),  8, 7, 8, 8),
            array($this->buildPaginator(1, 9),  9, 8, 9, 9),

            array($this->buildPaginator(10, 9),  1, 0,  1, 9),
            array($this->buildPaginator(10, 10), 1, 0,  1, 10),
            array($this->buildPaginator(10, 11), 1, 0,  1, 10),
            array($this->buildPaginator(10, 11), 2, 10, 11, 11),

            array($this->buildPaginator(10, 19), 1, 0,  1,  10),
            array($this->buildPaginator(10, 19), 2, 10, 11, 19),
            array($this->buildPaginator(10, 20), 1, 0,  1,  10),
            array($this->buildPaginator(10, 20), 2, 10, 11, 20),
            array($this->buildPaginator(10, 21), 1, 0,  1,  10),
            array($this->buildPaginator(10, 21), 2, 10, 11, 20),
            array($this->buildPaginator(10, 21), 3, 20, 21, 21),

            array($this->buildPaginator(50, 19), 1, 0,  1,  19),
            array($this->buildPaginator(50, 20), 1, 0,  1,  20),
            array($this->buildPaginator(50, 21), 1, 0,  1,  21),
            array($this->buildPaginator(50, 49), 1, 0,  1,  49),
            array($this->buildPaginator(50, 50), 1, 0,  1,  50),
            array($this->buildPaginator(50, 51), 1, 0,  1,  50),
            array($this->buildPaginator(50, 51), 2, 50, 51, 51),
        );
    }

    /**
     * Tests first and last items on a given page number.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestItemsOnPageData')]
    public function testItemsOnPage(Paginator $paginator, $pageNumber, $expectedOffset, $expectedFirst, $expectedLast)
    {
        $this->assertEquals($expectedOffset, $paginator->getOffset($pageNumber));
        $this->assertEquals($expectedFirst, $paginator->getFirstOnPage($pageNumber));
        $this->assertEquals($expectedLast, $paginator->getLastOnPage($pageNumber));
    }


    /**
     * Invalid item tests
     */

    /**
     * Tests that zero pagesize is invalid.
     *
     *
     */
    public function testZeroPagesize()
    {
        $this->expectException(OutOfRangeException::class);
        $this->buildPaginator(0, 0);
    }

    /**
     * Tests that negative pagesize is invalid.
     *
     *
     */
    public function testNegativePagesize()
    {
        $this->expectException(OutOfRangeException::class);
        $this->buildPaginator(-1, 0);
    }

    /**
     * Tests for zero total - any method that expects a page number should fail.
     *
     *
     */
    public function testZeroTotalOffset()
    {
        $this->expectException(OutOfRangeException::class);
        $paginator = $this->buildPaginator(10, 0);
        $paginator->getOffset(1);
    }

    /**
     * Tests for zero total - any method that expects a page number should fail.
     *
     *
     */
    public function testZeroTotalFirstOnPage()
    {
        $this->expectException(OutOfRangeException::class);
        $paginator = $this->buildPaginator(10, 0);
        $paginator->getFirstOnPage(1);
    }

    /**
     * Tests for zero total - any method that expects a page number should fail.
     *
     *
     */
    public function testZeroTotalLastOnPage()
    {
        $this->expectException(OutOfRangeException::class);
        $paginator = $this->buildPaginator(10, 0);
        $paginator->getLastOnPage(1);
    }

    /**
     * Tests that negative total is invalid.
     *
     *
     */
    public function testNegativeTotal()
    {
        $this->expectException(OutOfRangeException::class);
        $this->buildPaginator(10, -1);
    }


    /**
     * @return array
     */
    public function getTestPageNumberLowerBoundData()
    {
        return array(
            array($this->buildPaginator(1, 0),  -1),

            array($this->buildPaginator(1, 9),   0),
            array($this->buildPaginator(1, 9),  -1),

            array($this->buildPaginator(10, 9),   0),
            array($this->buildPaginator(10, 9),  -1),
            array($this->buildPaginator(10, 10),  0),
            array($this->buildPaginator(10, 10), -1),
            array($this->buildPaginator(10, 11),  0),
            array($this->buildPaginator(10, 11), -1),
        );
    }

    /**
     * Tests that requesting a page number below the minimum range is invalid.
     *
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPageNumberLowerBoundData')]
    public function testPageNumberLowerBound(Paginator $paginator, $pageNumber)
    {
        $this->expectException(OutOfRangeException::class);
        $paginator->getFirstOnPage($pageNumber);
    }


    /**
     * @return array
     */
    public function getTestPageNumberUpperBoundData()
    {
        return array(
            array($this->buildPaginator(1, 0),  1),

            array($this->buildPaginator(1, 9),  10),

            array($this->buildPaginator(10, 9),  2),
            array($this->buildPaginator(10, 9),  4),
            array($this->buildPaginator(10, 10), 2),
            array($this->buildPaginator(10, 10), 5),
            array($this->buildPaginator(10, 11), 3),
            array($this->buildPaginator(10, 11), 5),
        );
    }

    /**
     * Tests that requesting a page number above the minimum range is invalid.
     *
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPageNumberUpperBoundData')]
    public function testPageNumberUpperBound(Paginator $paginator, $pageNumber)
    {
        $this->expectException(OutOfRangeException::class);
        $paginator->getFirstOnPage($pageNumber);
    }
}
