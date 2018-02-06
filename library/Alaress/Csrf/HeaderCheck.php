<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Csrf;

/**
 * CSRF header check.
 *
 * Implements the CSRF header check as prescribed in
 * https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)_Prevention_Cheat_Sheet#Verifying_Same_Origin_with_Standard_Headers
 */
class HeaderCheck
{
    /**
     * Server vars ($_SERVER)
     *
     * @var array
     */
    private $serverVars;

    /**
     * A host that will always be considered valid.
     *
     * @var string|null
     */
    private $host;

    /**
     * Constructor
     *
     * @param array       $serverVars Server vars, usually from `$_SERVER`.
     *                                Contains HTTP headers in `HTTP_*` keys.
     * @param null|string $host       If set, this host is always considered
     *                                valid if it matches a source.
     */
    public function __construct(array $serverVars, ?string $host = null)
    {
        $this->serverVars = $serverVars;
        $this->host       = $host;
    }

    /**
     * Verify the request origin.
     *
     * The origin is valid if the source and target origin match.
     *
     * Source origin:
     * * Headers: Origin, or Referer if Origin is not set
     *
     * Target origin: either one of:
     * * `$this->host` value
     * * Headers: Host, or X-Forwarded-Host if Host is not set
     *
     * @return bool
     */
    public function verifyRequestOrigin() : bool
    {
        $sources = array_filter(
            array_map(
                [$this, 'getHostName',],
                [
                    $this->serverVars['HTTP_ORIGIN'] ?? $this->serverVars['HTTP_REFERER'] ?? null,
                ]
            )
        );

        $targets = array_filter(
            array_map(
                [$this, 'getHostName',],
                [
                    $this->host,
                    $this->serverVars['HTTP_HOST'] ?? $this->serverVars['HTTP_X_FORWARDED_HOST'] ?? null,
                ]
            )
        );

        // Check each combination; if any match, we have a match
        foreach ($sources as $source) {
            foreach ($targets as $target) {
                if ($source === $target) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Gets a host name out of a URL
     *
     * @param null|string $url
     * @return null|string The host name, or null if there's none in `$url`
     */
    private function getHostName(?string $url) : ?string
    {
        // Empty? Return empty
        if (null === $url) {
            return null;
        }

        // Need to add a scheme if there is none before we get a host,
        // otherwise it thinks a naked IP or hostname is a path.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (false === $scheme) {
            return null;
        }
        if (null === $scheme) {
            $url = 'http://'.$url;
        }

        // Now get a host
        $host = parse_url($url, PHP_URL_HOST);
        if (false === $host) {
            return null;
        }
        return $host;
    }
}
