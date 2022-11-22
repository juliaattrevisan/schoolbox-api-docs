<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Url;

use League\Uri\Components\Query;
use League\Uri\QueryString;
use League\Uri\UriInfo;
use League\Uri\UriModifier;
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
        $reformattedParams = [];
        foreach ($queryParameters as $key => $value) {
            // format params to array of [key, value] array
            $reformattedParams[] = [$key, $value];
        }
        /** @var Uri $uri */
        $uri = UriModifier::mergeQuery($this, QueryString::build($reformattedParams));
        return $uri;
    }

    /**
     * Get query parameters in key => value array
     * @return array
     */
    public function getQueryAsArray(): array
    {
        $queryArray = [];
        $query = Query::createFromUri($this);
        foreach ($query->getIterator() as $item) {
            $queryArray[$item[0]] = $item[1];
        }
        return $queryArray;
    }

    /**
     * @return bool
     */
    public function isAbsolutePath(): bool
    {
        return UriInfo::isAbsolutePath($this);
    }
}
