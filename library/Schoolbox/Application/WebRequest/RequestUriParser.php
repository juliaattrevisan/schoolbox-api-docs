<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\WebRequest;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use UnexpectedValueException;

/**
 * Consistently parse the REQUEST_URI from $_SERVER
 */
class RequestUriParser
{
    /**
     * Return 'REQUEST_URI' URL path from $_SERVER.
     *
     * Trailing '/' char always trimmed.
     *
     * @param array $serverVars
     * @return string
     * @throws InvalidArgumentException If no request URI found in server vars
     * @throws BadRequestException If we can explicitly determine the path is invalid - client error
     * @throws UnexpectedValueException If we cannot parse a path from request URI
     */
    public static function parseUrlPath(array $serverVars): string
    {
        $requestUri = $serverVars['REQUEST_URI'] ?? null;
        if ($requestUri === null) {
            throw new InvalidArgumentException("No request uri found in server variables");
        }

        // Multiple leading '/' chars?
        if (preg_match("#^/{2,}#", $requestUri)) {
            throw new BadRequestException("Invalid request URI '$requestUri'");
        }

        $parsedUrl = parse_url($requestUri, PHP_URL_PATH);

        # Unable to parse URL using normal processing?
        if ($parsedUrl === false) {
            // because parse_url returns false when the url contains ":"? Try replacing it.
            $parsedUrl = parse_url(str_replace(':', '%3A', $requestUri), PHP_URL_PATH);
            if ($parsedUrl !== false) {
                $parsedUrl = str_replace('%3A', ':', $parsedUrl);
            }
        }

        if ($parsedUrl === false) {
            throw new UnexpectedValueException("Unable to parse a URL path from URI '$requestUri'");
        }

        // Trim trailing /
        return preg_replace('|/$|', '', $parsedUrl);
    }
}
