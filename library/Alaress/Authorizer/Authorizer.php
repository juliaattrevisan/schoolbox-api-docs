<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer;

use Alaress\Web\Router\PathMatcher;
use Schoolbox\User;

class Authorizer extends PathMatcher
{

    /**
     * @var User
     */
    protected $user;

    /**
     * Adds a rule for a path.
     *
     * @param string                  $path A regexp to match a path against
     * @param Rule\IAuthorizationRule $rule The rule to check against.
     * @return $this
     */
    public function addRule($path, Rule\IAuthorizationRule $rule)
    {
        $this->add($path, $rule, array('auth'));
        return $this;
    }

    /**
     * Authorizes whether a user may access a given path.
     *
     * @param string $path   The path to check. Often, this refers to a controller
     *                       name, which may have been determined by a route
     *                       matched by Aura\Router\Router->match().
     * @param array  $params Any params to pass as part of the route.
     * @param User   $user   The user to check.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     */
    public function authorizePathForUser($path, $params = array(), User $user = null)
    {
        $this->setUser($user);
        $this->check($path, $params);
    }


    /**
     * Gets the result of calling an action.
     *
     * @param  Rule\IAuthorizationRule $callback
     * @param  array                   $params
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     * @return array
     */
    protected function getActionResult($callback, $params)
    {
        return $callback->authorize($params, $this->getUser());
    }

    /**
     * Quick boolean check for whether a path is authorized for a user.
     *
     * This method doesn't get any information about why the path isn't
     * authorized - if you need that use authorizePathForUser() and catch the
     * exception it throws.
     *
     * @see    $this->authorizePathForUser()
     * @param  string $path
     * @param  array  $params
     * @param  User   $user
     * @return bool
     */
    public function isPathAuthorizedForUser($path, $params = array(), User $user = null)
    {
        try {
            $this->authorizePathForUser($path, $params, $user);
        } catch (Failure\Base $e) {
            return false;
        }
        return true;
    }

    /**
     * @param  User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}
