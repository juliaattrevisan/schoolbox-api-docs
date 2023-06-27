<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Schoolbox\User;

/**
 * Set up the _links node with a bunch of permission-based urls that
 * $user can access
 */
abstract class AbstractLinks
{
    /**
     * Constructor
     *
     * @param Generator  $routeGenerator
     * @param Authorizer $authorizer
     * @param User       $currentUser
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser
    ) {
    }

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
