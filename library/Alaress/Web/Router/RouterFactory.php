<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Router;

use Aura\Router\Generator;
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;

/**
 * Implementation of Aura\Router\RouterFactory.
 *
 * This creates the router with instance of Alaress\Web\Router\RouteCollection
 * (which defines extra helper methods over Aura\Router\RouteCollection)
 *
 * @deprecated this ought not be needed with Aura.Router 3.x
 */
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
