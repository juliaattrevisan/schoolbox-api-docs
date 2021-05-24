<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Schoolbox\User;

class RouteLookupIfAuthorized
{

    /** @var RouteLookup */
    protected $routeLookup;

    /** @var IsRouteAuthorized */
    protected $isRouteAuthorized;


    /**
     * @param RouteLookup       $routeLookup
     * @param IsRouteAuthorized $isRouteAuthorized
     */
    public function __construct(RouteLookup $routeLookup, IsRouteAuthorized $isRouteAuthorized)
    {
        $this->routeLookup = $routeLookup;
        $this->isRouteAuthorized = $isRouteAuthorized;
    }


    /**
     * Performs a reverse lookup on the route given its name, and checks if that
     * path is accessible to the given user.
     *
     * @param  string $name   The name of the route to look up.
     * @param  array  $params Any params to pass as part of the route.
     * @param  User   $user   The user to authorize. If not set, defaults to the
     *                        currently logged in user.
     * @return string
     */
    public function __invoke($name, $params = array(), User $user = null)
    {
        if ($this->isRouteAuthorized->execute($name, $params, $user)) {
            return $this->routeLookup->execute($name, $params);
        }
        return '';
    }
}
