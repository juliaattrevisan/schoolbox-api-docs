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
use Twig_Environment;

abstract class RendererBase implements RendererInterface
{
    /**
     * The user who the event relates to
     *
     * @var \Schoolbox\User
     */
    var $user;

    /**
     * The user who is viewing this event
     *
     * @var \Schoolbox\User
     */
    var $viewer;

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
        $this->di = $di;
        $this->setUser($user);
        $this->setViewer($viewer);
    }


    /**
     * @param \Schoolbox\User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \Schoolbox\User $viewer
     */
    public function setViewer($viewer)
    {
        $this->viewer = $viewer;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getViewer()
    {
        return $this->viewer;
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
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $this->getViewer())) {
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
