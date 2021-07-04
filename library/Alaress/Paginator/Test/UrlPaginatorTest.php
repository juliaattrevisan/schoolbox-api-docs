<?php

/**
 * Unit tests for Alaress\Paginator\UrlGenerator class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Paginator\Test;

use Alaress\Paginator\Paginator;
use Alaress\Paginator\UrlPaginator;
use Laminas\Uri\Uri;
use PHPUnit\Framework\TestCase;

class UrlPaginatorTest extends TestCase
{

    /**
     * Simple factory for a test UrlPaginator object.
     *
     * @param  int    $pageSize
     * @param  int    $total
     * @param  string $url
     * @param  string $param
     * @return UrlPaginator
     */
    protected function buildPaginator($pageSize, $total, $url, $param)
    {
        $paginator = new Paginator();
        $paginator->setPageSize($pageSize)->setTotal($total);
        $urlPaginator = new UrlPaginator();
        $urlPaginator
            ->setPaginator($paginator)
            ->setUrl(new Uri($url))
            ->setParam($param);
        return $urlPaginator;
    }

    protected function getPathFromUrl($url)
    {
        return parse_url($url, PHP_URL_PATH);
    }

    protected function getQueryFromUrl($url)
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        return $query;
    }

    /**
     * Tests for basic URL generation
     */
    public function testBasicUrl()
    {
        $paginator = $this->buildPaginator(50, 300, 'https://www.google.com', 'p');
        $this->assertEquals('https://www.google.com/?p=1', $paginator->getUrlForPage(1));
        $this->assertEquals('https://www.google.com/?p=2', $paginator->getUrlForPage(2));
    }

    /**
     * Tests for URL generation from an incomplete URL
     */
    public function testIncompleteUrl()
    {
        $paginator = $this->buildPaginator(50, 300, '/resource-booking/item?search=b', 'page');

        $url = $paginator->getUrlForPage(1);
        $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
        $this->assertEquals(array('search' => 'b', 'page' => 1), $this->getQueryFromUrl($url));

        $url = $paginator->getUrlForPage(2);
        $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
        $this->assertEquals(array('search' => 'b', 'page' => 2), $this->getQueryFromUrl($url));
    }

    /**
     * Tests for URL generation from a URL which already has a page number
     */
    public function testUrlWithPageAlready()
    {
        $paginator = $this->buildPaginator(50, 300, '/resource-booking/item?search=b&page=3', 'page');

        $url = $paginator->getUrlForPage(1);
        $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
        $this->assertEquals(array('search' => 'b', 'page' => 1), $this->getQueryFromUrl($url));

        $url = $paginator->getUrlForPage(2);
        $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
        $this->assertEquals(array('search' => 'b', 'page' => 2), $this->getQueryFromUrl($url));
    }

    /**
     * Tests for checking that getPageUrls() and getPageUrlsBetween() creates
     * the correct number of pages, and correct URLs
     */
    public function testPageUrlCount()
    {
        $paginator = $this->buildPaginator(50, 300, '/resource-booking/item?search=b', 'page');
        $this->assertCount(6, $paginator->getPageUrls());

        $urlsBetween = $paginator->getPageUrlsBetween(3, 5);
        $this->assertCount(3, $urlsBetween);
        foreach ($urlsBetween as $page => $url) {
            $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
            $this->assertEquals(array('search' => 'b', 'page' => $page), $this->getQueryFromUrl($url));
        }
    }

    /**
     * Tests for checking that getPageUrls() and getPageUrlsBetween() work as
     * expected for empty paginators
     */
    public function testEmptyPaginatorPageUrlCount()
    {
        $paginator = $this->buildPaginator(50, 0, '/resource-booking/item?search=b', 'page');
        $this->assertCount(0, $paginator->getPageUrls());

        // Generating URLs for pages that don't exist should still work
        $url = $paginator->getUrlForPage(2);
        $this->assertEquals('/resource-booking/item', $this->getPathFromUrl($url));
        $this->assertEquals(array('search' => 'b', 'page' => 2), $this->getQueryFromUrl($url));
    }
}
