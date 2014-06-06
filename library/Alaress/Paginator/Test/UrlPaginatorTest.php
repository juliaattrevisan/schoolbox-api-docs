<?php
/**
 * Unit tests for Alaress\Paginator\UrlGenerator class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Paginator\Test;
use Alaress\Paginator\Paginator;
use Alaress\Paginator\UrlPaginator;
use Zend\Uri\Uri;
use PHPUnit_Framework_TestCase;

class UrlPaginatorTest extends PHPUnit_Framework_TestCase {

    /**
     * Tests basic URL generation.
     */
    public function testUrlPaginator() {
        $basePaginator = new Paginator();
        $basePaginator->setPageSize(50)->setTotal(300);

        // Basic URL
        $urlPaginator = new UrlPaginator();
        $urlPaginator
            ->setPaginator($basePaginator)
            ->setUrl(new Uri('https://www.google.com'))
            ->setParam('p');

        $this->assertEquals('https://www.google.com/?p=1', $urlPaginator->getUrlForPage(1)->toString());
        $this->assertEquals('https://www.google.com/?p=2', $urlPaginator->getUrlForPage(2)->toString());

        // A more complicated but incomplete URL
        $urlPaginator = new UrlPaginator();
        $urlPaginator
            ->setPaginator($basePaginator)
            ->setUrl(new Uri('/resource-booking/item?search=b'))
            ->setParam('page');

        $url = $urlPaginator->getUrlForPage(1);
        $this->assertEquals('/resource-booking/item', $url->getPath());
        $this->assertEquals(array('search' => 'b', 'page' => 1), $url->getQueryAsArray());

        $url = $urlPaginator->getUrlForPage(2);
        $this->assertEquals('/resource-booking/item', $url->getPath());
        $this->assertEquals(array('search' => 'b', 'page' => 2), $url->getQueryAsArray());

        // Test iteration
        $urlsBetween = $urlPaginator->getPageUrlsBetween(3, 5);
        $this->assertCount(3, $urlsBetween);
        foreach ($urlsBetween as $page => $url) {
            $this->assertEquals('/resource-booking/item', $url->getPath());
            $this->assertEquals(array('search' => 'b', 'page' => $page), $url->getQueryAsArray());
        }
    }
}
