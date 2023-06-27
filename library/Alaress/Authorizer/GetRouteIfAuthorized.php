<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Authorizer;

use Aura\Router\Generator;
use Schoolbox\User;

/**
 * Trait for the common `getRouteIfAuthorized` function.
 */
trait GetRouteIfAuthorized
{
    /**
     * The router object, as required to generate a link to content.
     */
    protected Generator $routeGenerator;


    /**
     * The authorizer object, as required to determine whether a user is
     * authorized to access a link.
     */
    protected Authorizer $authorizer;


    /**
     * The user who is being authorized to access a link.
     */
    protected User $currentUser;


    /**
     * Helper function for outputting a route, only if it is authorized.
     *
     * @param string $route
     * @param array  $params
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection Should never happen as `$route` should never not be defined
     */
    protected function getRouteIfAuthorized(string $route, array $params): ?string
    {
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $this->currentUser)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->routeGenerator->generate($route, $params);
        }
        return null;
    }
}
