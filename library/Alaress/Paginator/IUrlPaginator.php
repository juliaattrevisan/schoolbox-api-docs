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

interface IUrlPaginator extends IPaginator {

    /**
     * Creates a URL out of whatever the URL I passed is.
     *
     * @param  int $page
     * @return UriInterface
     */
    public function getUrlForPage($page);

    /**
     * @return array|UriInterface[]
     */
    public function getPageUrls();

    /**
     * @param int $from
     * @param int $to
     *
     * @return array|UriInterface[]
     */
    public function getPageUrlsBetween($from, $to);

    /**
     * @param  string $param
     * @return $this
     */
    public function setParam($param);

    /**
     * @param  UriInterface $url
     * @return $this
     */
    public function setUrl($url);

}
