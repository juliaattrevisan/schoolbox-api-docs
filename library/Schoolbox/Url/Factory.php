<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Url;

use Schoolbox\IConfigLoader;

/**
 * Factory for building URLs based upon Schoolbox configuration.
 */
class Factory
{
    /**
     * Schoolbox configuration
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * TODO allow this to be built from a `\Schoolbox\Instance` instead
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Build a URI object with this Schoolbox instance's fully-qualified domain
     * name.
     *
     * Note that the URI returned will always be a URL: the addition of a
     * fully-qualified domain name sets the URI's host and thus defines a
     * location on a network: this is enough to make this URI a URL.
     * (@see https://www.guru99.com/url-vs-uri-difference.html)
     *
     * @return Uri
     */
    public function buildFullyQualified(): Uri
    {
        $host = trim("{$this->config->get('site_hostname')}.{$this->config->get('site_domain')}", '. ');

        // adding scheme will set host as localhost
        if (empty($host)) {
            return new Uri();
        }

        return (new Uri())
            ->withScheme('https')
            ->withHost($host);
    }
}
