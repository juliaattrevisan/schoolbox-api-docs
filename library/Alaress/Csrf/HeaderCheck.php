<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Csrf;

class HeaderCheck
{
    /**
     * Server vars ($_SERVER)
     *
     * @var array
     */
    private $serverVars;

    /**
     * Hard-coded hostname
     *
     * @var string|null
     */
    private $host;

    public function __construct(array $serverVars, ?string $host = null)
    {
        $this->serverVars = $serverVars;
        $this->host       = $host;
    }

    /**
     * Do the source and target headers match?
     *
     * @return bool
     */
    public function check() : bool
    {
        // Valid sources: origin or referer header
        $sources = array_filter(
            array_map(
                [$this, 'getHostName',],
                [
                    $this->serverVars['HTTP_ORIGIN'] ?? $this->serverVars['HTTP_REFERER'] ?? null,
                ]
            )
        );

        // Valid targets: can be configured; otherwise host or x-forwarded-host
        $targets = array_filter(
            array_map(
                [$this, 'getHostName',],
                [
                    $this->host,
                    $this->serverVars['HTTP_HOST'] ?? $this->serverVars['HTTP_X_FORWARDED_HOST'] ?? null,
                ]
            )
        );

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
        if (null === $scheme) {
            $url = 'http://'.$url;
        }

        // Now get a host
        return parse_url($url, PHP_URL_HOST);
    }
}
