<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @param string $routeName
     * @param array  $routeArgs
     */
    public function __construct(string $routeName, array $routeArgs = [])
    {
        $this->name = $routeName;
        $this->args = $routeArgs;
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
        return $this->auth->isPathAuthorizedForUser($this->name, $this->args, $this->user);
    }


}
