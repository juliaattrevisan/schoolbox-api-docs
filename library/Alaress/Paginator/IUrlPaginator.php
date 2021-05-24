<?php

/**
 * A paginator object that contains a URL, and knows how to modify that URL to
 * point to a page within that URL.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Paginator;

interface IUrlPaginator extends IPaginator
{

    /**
     * Creates a URL out of whatever the URL I passed is.
     *
     * @param  int $page
     * @return string
     */
    public function getUrlForPage($page);

    /**
     * @return array|string[]
     */
    public function getPageUrls();

    /**
     * @param int $from
     * @param int $to
     *
     * @return array|string[]
     */
    public function getPageUrlsBetween($from, $to);

    /**
     * @param  string $param
     * @return $this
     */
    public function setParam($param);

    /**
     * @param  string $url
     * @return $this
     */
    public function setUrl($url);
}
