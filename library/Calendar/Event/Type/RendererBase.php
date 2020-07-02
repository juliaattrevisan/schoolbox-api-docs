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
     * @var \Schoolbox\User
     */
    protected $user;

    /**
     * The user who is viewing this event
     *
     * @var \Schoolbox\User
     */
    protected $viewer;

    /**
     * DI container
     *
     * TODO once template engine is removed, remove this and only inject the authorizer and router
     * Whilst this is better than including SitePage this is still iffy:
     * including a service locator introduces unknowable hidden dependencies.
     *
     * @var ContainerInterface
     */
    private $di;


    /**
     * Constructor
     *
     * @param ContainerInterface $di
     * @param User               $user
     * @param User               $viewer
     */
    public function __construct(ContainerInterface $di, User $user, User $viewer)
    {
        $this->di         = $di;
        $this->user       = $user;
        $this->viewer     = $viewer;
    }


    /**
     * Helper function - gets whether access to a route is allowed.
     *
     * TODO inject only what is required for this, not the entire DI
     *
     * @param string $name
     * @param array  $params
     *
     * @return bool|string False if not authorized, a URL if it is.
     */
    public function getRouteIfAuthorized($name, array $params = [])
    {
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $this->viewer)) {
            return $this->getRouter()->generate($name, $params);
        }
        return false;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer(): Authorizer
    {
        return $this->di->get('authorizer');
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->di->get('router');
    }
}
