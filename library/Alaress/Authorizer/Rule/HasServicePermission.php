<?php
/**
 * Base authorization method - contains a session object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

class HasServicePermission implements IAuthorizationRule {

    protected $service;
    protected $accessLevel;

    public function __construct($service, $accessLevel) {
        $this->service     = $service;
        $this->accessLevel = $accessLevel;
    }

    public function authorize(User $user = null) {
        if (!$user instanceof User) {
            throw new Forbidden();
        }
        // Check this user's permissions
        // I dunno if user's service access level can be checked from here.
//        var_dump($user);
    }

}
