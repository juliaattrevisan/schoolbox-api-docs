<?php
/**
 * Authorization failure - user was authenticated, but their session has timed
 * out.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Failure;

class AuthenticationTimedOut extends Unauthenticated {}
