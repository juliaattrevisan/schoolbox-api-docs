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
     * @param string $path The path to check. Often, this refers to a controller
     *                     name, which may have been determined by a route
     *                     matched by Aura\Router\Router->match().
     * @param User   $user The user to check.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     */
    public function authorizePathForUser($path, User $user = null) {
        foreach ($this->ruleList as $rule) {
            if (preg_match($rule['path'], $path)) {
                /** @var Rule\IAuthorizationRule $authRule */
                $authRule = $rule['rule'];
                $authRule->authorize($user);
            }
        }
    }

}
