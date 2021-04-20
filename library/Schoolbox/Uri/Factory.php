<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Uri;

use Schoolbox\IConfigLoader;
use Zend\Uri\Uri;
use Zend\Uri\UriInterface;

/**
 * Factory for building URIs based upon Schoolbox configuration.
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
     * @return UriInterface
     */
    public function buildFullyQualified(): UriInterface
    {
        $host = trim("{$this->config->get('site_hostname')}.{$this->config->get('site_domain')}", '. ');
        return (new Uri())->setHost($host);
    }
}
