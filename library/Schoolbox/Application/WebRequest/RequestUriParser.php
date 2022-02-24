<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\WebRequest;

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
     */
    public static function parseUrlPath(array $serverVars): string
    {
        $parsedUrl = parse_url($serverVars['REQUEST_URI'], PHP_URL_PATH);

        if (!$parsedUrl) {
            // because parse_url returns false when the url contains ":", replacing it.
            $parsedUrl = parse_url(str_replace(':', '%3A', $serverVars['REQUEST_URI']), PHP_URL_PATH);
            $parsedUrl = str_replace('%3A', ':', $parsedUrl);
        }

        // Trim trailing /
        return preg_replace('|/$|', '', $parsedUrl);
    }
}
