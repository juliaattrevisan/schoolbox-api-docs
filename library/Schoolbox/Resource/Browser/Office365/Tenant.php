<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Office365;

use InvalidArgumentException;

class Tenant
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $sitePath;

    /** @var string */
    protected $resource;

    /** @var string */
    protected $baseUri;


    /**
     * @param string $tenant
     * @param string $sitePath
     * @param string $version
     */
    public function __construct($tenant, $sitePath = null, $version = 'v1.0')
    {
        if (!$tenant) {
            throw new InvalidArgumentException('A tenant must be provided');
        }

        if ($sitePath) {
            $this->resource = "https://$tenant.sharepoint.com";
            $this->baseUri  = "{$this->resource}/$sitePath/_api/$version";
        } else {
            $this->resource = "https://$tenant-my.sharepoint.com";
            $this->baseUri  = "{$this->resource}/_api/$version/me";
        }
    }


    /**
     * @return string
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }


    /**
     * @return string
     */
    public function getResource()
    {
        return $this->resource;
    }


    /**
     * @return string
     */
    public function getSitePath()
    {
        return $this->sitePath;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
