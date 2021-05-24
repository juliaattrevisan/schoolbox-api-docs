<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Json\Api;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Api\Link;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Schoolbox\User;

class AuraLink implements Link
{
    /** @var string */
    private $name;

    /** @var array */
    private $args;

    /** @var Router */
    private $router;

    /** @var Authorizer */
    private $auth;

    /** @var User */
    private $user;

    /**
     * If false, don't perform an auth check on this link.
     *
     * @var bool
     */
    private $doAuth;


    /**
     * @param string $routeName
     * @param array  $routeArgs
     * @param bool   $doAuth
     */
    public function __construct(string $routeName, array $routeArgs = [], bool $doAuth = true)
    {
        $this->name   = $routeName;
        $this->args   = $routeArgs;
        $this->doAuth = $doAuth;
    }


    /**
     * Post-construction configuration
     *
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User|null  $user
     */
    public function configure(Router $router, Authorizer $authorizer, ?User $user)
    {
        $this->router = $router;
        $this->auth   = $authorizer;
        $this->user   = $user;
    }


    /**
     * Return this link's href
     *
     * @throws RouteNotFound
     * @return string
     */
    public function getHref(): string
    {
        return $this->router->generate($this->name, $this->args);
    }


    /**
     * Return whether or not the current session user is allowed to access this link.
     * Make sure you return NULL if the answer is "I don't know"
     *
     * @return bool|null
     */
    public function getAuth(): ?bool
    {
        if (!$this->doAuth) {
            return null;
        }
        return $this->auth->isPathAuthorizedForUser($this->name, $this->args, $this->user);
    }
}
