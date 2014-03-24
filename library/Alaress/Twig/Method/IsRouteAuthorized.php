<?php
/**
 * Twig function for checking whether a route is authorized for a user.
 *
 * By default checks the currently logged-in user, but can accept a different
 * user as a parameter.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Twig\Method;

use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Session\Session;
use Schoolbox\User;

class IsRouteAuthorized {

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
     * @param  string $name
     * @param  array  $data
     * @param  User   $user If not set, defaults to the current user.
     * @return bool
     */
    public function execute($name, User $user = null, $data = array()) {
        if (!($user instanceof User)) {
            $user = $this->getSession()->getUser();
        }
        // Ensure format is set to something, even if empty.
        if (!isset($data['format'])) {
            $data['format'] = '';
        }
        $route = $this->getRouter()->generate($name, $data);
        return $this->getAuthorizer()->isPathAuthorizedForUser($route, $user);
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router) {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter() {
        return $this->router;
    }

    /**
     * @param  Authorizer $authorizer
     * @return $this
     */
    public function setAuthorizer($authorizer) {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer() {
        return $this->authorizer;
    }

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session) {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession() {
        return $this->session;
    }

}
