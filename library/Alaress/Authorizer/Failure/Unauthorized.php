<?php
/**
 * Authorization failure - the authenticated user does not have permission
 * to access this resource.
 *
 * This failure should be thrown when there is an authenticated user, but they
 * do not have sufficient permissions to access the requested resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Failure;

class Unauthorized extends Base {}
