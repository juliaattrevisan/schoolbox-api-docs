<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Robots;

use Alaress\Web\Controller\Base;
use Schoolbox\IConfigLoader;

class Controller extends Base
{
    /**
     *
     */
    public function __construct(private IConfigLoader $config)
    {
    }
    /**
     * :robot: endpoint for search engine to hit and confirm if we are allowing them access to index / scrape the instance./
     *
     * @api   GET /robots.txt
     * @since 23.0.1
     *
     */
    public function getRobots(): void
    {
        $robotAllow = "
            User-Agent: *
            Allow: /
            ";
        $robotDeny = "
            User-Agent: *
            Disallow: /
            ";

        // Are we blocking web crawlers, or allowing them?
        if ($this->config->get('web_crawlers_enabled')) {
            echo $robotAllow;
        } else {
            echo $robotDeny;
        }
        die();
    }
}
