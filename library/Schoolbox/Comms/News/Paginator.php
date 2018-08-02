<?php
/**
 * Paginator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Paginator\UrlPaginator;
use Zend\Uri\Uri;

class Paginator extends UrlPaginator
{
    /**
     * Creates a URL out of whatever the URL I passed is.
     *
     * @param  int $page
     * @return string
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriException
     */
    public function getUrlForPage($page)
    {
        // Building a URL using PHP builtins is a complete pain!
        // (There's no http_build_str equivalent for parse_str)
        // That's why I use Zend\Uri internally.
        $url = new Uri($this->url);
        $query = $url->getQueryAsArray();
        $query[$this->param] = $page;

        $url->setQuery(array_intersect_key($query, [
            Querystring::TOPIC_SLUG_PARAM => '',
            Querystring::PAGE_NUMBER_PARAM => ''
        ]));

        return $url->toString();
    }
}
