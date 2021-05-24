<?php

/**
 * Authorization failure - user was authenticated, but their session has timed
 * out.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Failure;

class AuthenticationTimedOut extends Unauthenticated
{
}
