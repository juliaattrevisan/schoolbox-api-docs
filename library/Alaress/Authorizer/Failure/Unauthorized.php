<?php

/**
 * Authorization failure - the authenticated user does not have permission
 * to access this resource.
 *
 * This failure should be thrown when there is an authenticated user, but they
 * do not have sufficient permissions to access the requested resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Failure;

use Schoolbox\Error\Mapper as ErrorMapper;

class Unauthorized extends Base
{
    /**
     * Default error code - corresponds to message "you do not have permission..."
     *
     * @var int
     */
    protected $code = ErrorMapper::ERROR_PERM_PAGE_VIEW;
}
