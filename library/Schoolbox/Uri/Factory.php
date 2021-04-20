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

class Factory
{
    public function buildFullyQualifiedFromConfig(IConfigLoader $config): UriInterface
    {
        $host = trim("{$config->get('site_hostname')}.{$config->get('site_domain')}");
        return (new Uri())->setHost($host);
    }
}
