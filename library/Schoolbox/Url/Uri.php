<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Url;

use Psr\Http\Message\UriInterface;

class Uri extends \GuzzleHttp\Psr7\Uri implements UriInterface
{
    /**
     * Set query parameters in key => value array
     * @param array $queryParameters
     * @return self
     */
    public function appendQueryParameters(array $queryParameters): self
    {
        $parameters = $this->getQueryAsArray();
        /** @var Uri $uri */
        $uri = $this->withQuery(http_build_query(array_merge($parameters, $queryParameters)));
        return $uri;
    }

    /**
     * Get query parameters in key => value array
     * @return array
     */
    public function getQueryAsArray(): array
    {
        return \GuzzleHttp\Psr7\Query::parse($this->getQuery());
    }

    /**
     * @return bool
     */
    public function isAbsolutePath(): bool
    {
        return self::isAbsolutePathReference($this);
    }
}
