<?php
/**
 * This authorization method checks whether the user that is passed is a
 * superuser.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

class IsSuperuser implements IAuthorizationRule {

    public function authorize(User $user = null) {
        if ($user instanceof User && $user->isSuperuser()) {
            throw new Forbidden();
        }
    }

}
