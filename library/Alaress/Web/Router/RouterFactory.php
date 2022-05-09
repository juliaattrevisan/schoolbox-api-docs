<?php

/**
 * Implementation of Aura\Router\RouterFactory.
 *
 * This creates the router with instance of Alaress\Web\Router\RouteCollection
 * (which defines extra helper methods over Aura\Router\RouteCollection)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Router;

use Aura\Router\Generator;
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;

class RouterFactory
{
    /**
     * Returns a new Router instance.
     *
     * @return Router
     */
    public function newInstance()
    {
        return new Router(new RouteCollection(new RouteFactory()), new Generator());
    }
}
