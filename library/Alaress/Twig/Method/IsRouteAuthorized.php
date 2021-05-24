<?php

/**
 * Twig function for checking whether a route is authorized for a user.
 *
 * By default checks the currently logged-in user, but can accept a different
 * user as a parameter.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Session\Session;
use Schoolbox\User;

class IsRouteAuthorized
{

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Authorizer
     */
    protected $authorizer;

    /**
     * @var Session
     */
    protected $session;

    /**
     * Performs a reverse lookup on the route given its name, and checks if that
     * path is accessible to the given user.
     *
     * @param  string $name   The name of the route to look up.
     * @param  array  $params Any params to pass as part of the route.
     * @param  User   $user   The user to authorize. If not set, defaults to the
     *                        currently logged in user.
     * @return bool
     */
    public function execute($name, $params = array(), User $user = null)
    {
        if (!($user instanceof User)) {
            $user = $this->getSession()->getAuthUser();
        }
        return $this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $user);
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param  Authorizer $authorizer
     * @return $this
     */
    public function setAuthorizer($authorizer)
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer()
    {
        return $this->authorizer;
    }

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }
}
