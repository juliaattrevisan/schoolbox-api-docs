<?php

/**
 * Paginator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Paginator\UrlPaginator;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriException;
use Zend\Uri\Uri;

class Paginator extends UrlPaginator
{
    /**
     * Creates a URL out of whatever the URL I passed is.
     *
     * @param  int $page
     * @return string
     * @throws InvalidArgumentException
     * @throws InvalidUriException
     */
    public function getUrlForPage($page): string
    {
        // Building a URL using PHP builtins is a complete pain!
        // (There's no http_build_str equivalent for parse_str)
        // That's why I use Zend\Uri internally.
        $url = new Uri($this->url);
        $query = $url->getQueryAsArray();
        $query[$this->param] = $page;

        $url->setQuery(array_intersect_key($query, [
            Querystring::TOPIC_SLUG => '',
            Querystring::PAGE_NUMBER => '',
            Querystring::COMPONENT_ID => '',
            Querystring::FOLDER => '',
        ]));

        return $url->toString();
    }
}
