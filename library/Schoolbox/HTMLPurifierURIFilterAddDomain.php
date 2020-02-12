<?php
/**
 *
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox;

use HTMLPurifier_URIFilter;

/**
 * Custom HTMLPurifier URIFilter to add domain name
 * @package Schoolbox
 */
class HTMLPurifierURIFilterAddDomain extends HTMLPurifier_URIFilter
{
    /**
     * @var string
     * Name of this filter
     */
    public $name = 'AddDomainName';

    /**
     * Domain name to append
     * @var string
     */
    public $domain;

    /**
     * @inheritDoc
     */
    public function filter(&$uri, $config, $context)
    {
        if ($this->name && !$uri->host) {
            $uri->path = $this->domain . $uri->path;
            return true;
        }
        return false;
    }
}
