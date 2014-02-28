<?php
/**
 * This authorization method checks whether a user is passed.
 *
 * Its main use is to check whether a user is even logged in.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Schoolbox\User;

class IsLoggedIn extends BaseRule {

    public function authorize(User $user = null) {
        return ($user instanceof User);
    }

}
