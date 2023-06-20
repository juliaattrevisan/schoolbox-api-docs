<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;

/**
 * Twig function for outputting a route.
 */
class RouteLookup
{
    /**
     * Constructor
     *
     * @param Generator $routeGenerator
     */
    public function __construct(private Generator $routeGenerator)
    {
    }

    /**
     * Performs a reverse lookup on the route given its name, and creates a URL.
     *
     * @param  string $name The name of the route to look up.
     * @param  array  $data Any params to pass as part of the route.
     * @return false|string
     */
    public function execute($name, $data = array())
    {
        // Ensure format is set to something, even if empty.
        if (!isset($data['format'])) {
            $data['format'] = '';
        }

        try {
            return $this->routeGenerator->generate($name, $data);
        } catch (RouteNotFound $e) {
            return false;
        }
    }
}
