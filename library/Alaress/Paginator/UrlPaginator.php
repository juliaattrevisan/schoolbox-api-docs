<?php
/**
 * A paginator object that contains a URL, and knows how to modify that URL to
 * point to a page within that URL.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Paginator;

use OutOfRangeException;
use Zend\Uri\UriInterface;

class UrlPaginator implements IUrlPaginator {

    /**
     * The URL to use to generate page URLs.
     *
     * @var UriInterface
     */
    protected $url;

    /**
     * The query parameter that refers to the page name.
     *
     * @var string
     */
    protected $param;

    /**
     * The base paginator object.
     *
     * @var IPaginator
     */
    protected $paginator;


    /**
     * Creates a URL out of whatever the URL I passed is.
     *
     * @param  int $page
     * @return UriInterface
     */
    public function getUrlForPage($page) {
        $url = clone $this->url;
        $query = $url->getQueryAsArray();
        $query[$this->param] = $page;
        $url->setQuery($query);
        return $url;
    }

    /**
     * @return array|UriInterface[]
     */
    public function getPageUrls() {
        return $this->getPageUrlsBetween(1, $this->getPageCount());
    }

    /**
     * @param int $from
     * @param int $to
     *
     * @return array|UriInterface[]
     */
    public function getPageUrlsBetween($from, $to) {
        $from   = max($from, 1);
        $to     = min($to,   $this->getPageCount());
        $result = array();
        if ($to > 0) {
            foreach (range($from, $to) as $page) {
                $result[$page] = $this->getUrlForPage($page);
            }
        }
        return $result;
    }


    /**
     * @param  IPaginator $paginator
     * @return $this
     */
    public function setPaginator($paginator) {
        $this->paginator = $paginator;
        return $this;
    }

    /**
     * @return IPaginator
     */
    public function getPaginator() {
        return $this->paginator;
    }

    /**
     * @param  string $param
     * @return $this
     */
    public function setParam($param) {
        $this->param = $param;
        return $this;
    }

    /**
     * @return string
     */
    public function getParam() {
        return $this->param;
    }

    /**
     * @param  UriInterface $url
     * @return $this
     */
    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    /**
     * @return UriInterface
     */
    public function getUrl() {
        return $this->url;
    }


    /**
     * IPaginator method implementations - all passed to the internal paginator.
     */

    /**
     * Gets the 0-indexed offset of the first item on page $pageNumber.
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getOffset($pageNumber) {
        return $this->paginator->getOffset($pageNumber);
    }

    /**
     * Gets the first item on the page
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getFirstOnPage($pageNumber) {
        return $this->paginator->getFirstOnPage($pageNumber);
    }

    /**
     * Gets the last item on the page
     *
     * @param  int $pageNumber
     * @return int
     * @throws OutOfRangeException if $pageNumber is outside valid page range
     */
    public function getLastOnPage($pageNumber) {
        return $this->paginator->getLastOnPage($pageNumber);
    }

    /**
     * Gets the number of pages.
     *
     * @return int
     */
    public function getPageCount() {
        return $this->paginator->getPageCount();
    }

    /**
     * @return int
     */
    public function getPageSize() {
        return $this->paginator->getPageSize();
    }

    /**
     * @param  int   $pageSize
     * @return $this
     * @throws OutOfRangeException if $pageSize <= 0
     */
    public function setPageSize($pageSize) {
        $this->paginator->setPageSize($pageSize);
        return $this;
    }

    /**
     * @param  int   $total
     * @return $this
     * @throws OutOfRangeException if $total <= 0
     */
    public function setTotal($total) {
        $this->paginator->setTotal($total);
        return $this;
    }

}
