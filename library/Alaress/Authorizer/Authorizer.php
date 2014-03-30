<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Alaress\Authorizer;
use Schoolbox\User;

class Authorizer {

    /**
     * A list of rules that have been defined.
     *
     * @var array
     */
    protected $ruleList = array();

    /**
     * Adds a rule for a path.
     *
     * @param string                  $path       A regexp to match a path against
     * @param Rule\IAuthorizationRule $rule       The rule to check against.
     * @param int                     $statusCode (not yet implemented)
     * @return $this
     */
    public function addRule($path, Rule\IAuthorizationRule $rule, $statusCode = null) {
        $this->ruleList[] = array(
            'path'       => $path,
            'rule'       => $rule,
            'statusCode' => $statusCode,
        );
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
    public function authorizePathForUser($path, $params = array(), User $user = null) {
        foreach ($this->ruleList as $rule) {
            if (preg_match($rule['path'], $path)) {
                /** @var Rule\IAuthorizationRule $authRule */
                $authRule = $rule['rule'];
                $authRule->authorize($params, $user);
            }
        }
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
    public function isPathAuthorizedForUser($path, $params = array(), User $user = null) {
        try {
            $this->authorizePathForUser($path, $params, $user);
        }
        catch (\Alaress\Authorizer\Failure\Base $e) {
            return false;
        }
        return true;
    }

}
