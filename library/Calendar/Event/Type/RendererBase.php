<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type;

use Alaress\Authorizer\Authorizer;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\User;

abstract class RendererBase implements RendererInterface
{
    /**
     * The user who the event relates to
     *
     * @var User
     */
    protected $user;


    /**
     * The user who is viewing this event
     *
     * @var User
     */
    protected $viewer;


    /**
     * @var Authorizer
     */
    private $authorizer;


    /**
     * @var Router
     */
    private $router;


    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param Router     $router
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Router $router, User $user, User $viewer)
    {
        $this->authorizer = $authorizer;
        $this->router     = $router;
        $this->user       = $user;
        $this->viewer     = $viewer;
    }


    /**
     * Helper function - gets whether access to a route is allowed.
     *
     * @param string $name
     * @param array  $params
     *
     * @return bool|string False if not authorized, a URL if it is.
     */
    public function getRouteIfAuthorized($name, array $params = [])
    {
        if ($this->authorizer->isPathAuthorizedForUser($name, $params, $this->viewer)) {
            return $this->router->generate($name, $params);
        }
        return false;
    }
}
