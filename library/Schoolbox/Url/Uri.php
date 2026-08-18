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
     * Build a URL from a configured base and a path relative to it.
     *
     * A base taken from configuration is not necessarily a bare host: it can
     * carry a path prefix (`instance.isams.cloud/api/batch/1.0`) and can omit
     * the scheme. Such a value cannot be given to `withHost()`, which since
     * `guzzle/psr7` 2.12.3 requires a bare host, so the URL is built from a
     * string instead and given a scheme when the base does not name one.
     *
     * @see https://github.com/guzzle/psr7/blob/3.0/CHANGELOG.md#2123---2026-06-23
     *
     * @param string $base          Base URL: a host, optionally with a scheme and a path prefix
     * @param string $path          Path to append to the base
     * @param string $defaultScheme Scheme to apply when the base does not name one
     * @return self
     */
    public static function fromBaseAndPath(string $base, string $path, string $defaultScheme = 'https'): self
    {
        $url = rtrim($base, "/ \n\r\t\v\x00") . '/' . ltrim($path, '/');

        if (!parse_url($url, PHP_URL_SCHEME)) {
            $url = "{$defaultScheme}://{$url}";
        }

        return new self($url);
    }

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
