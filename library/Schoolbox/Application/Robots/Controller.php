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
     * :robot: endpoint for search engine to hit and confirm if we are allowing them access to index / scrape the instance./
     *
     * @api   GET /robots.txt
     * @since 23.0.1
     *
     */
    public function getRobots(): void
    {
        // Are we blocking web crawlers, or allowing them? if not Prod, always block
        $this->response = $this
            ->responseFromContent(
                $this->getConfigLoader()->get('web_crawlers_enabled')
                    ? "User-Agent: *\nAllow: /\nDisallow: /feedback\nDisallow: /passwordReset\n"
                    : "User-Agent: *\nDisallow: /\n"
            )
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
