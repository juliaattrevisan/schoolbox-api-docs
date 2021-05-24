<?php

/**
 * Authorization failure - no user is authenticated.
 *
 * This failure should be thrown when a page requires a logged in user,
 * but no user is logged in.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Failure;

class Unauthenticated extends Base
{
}
