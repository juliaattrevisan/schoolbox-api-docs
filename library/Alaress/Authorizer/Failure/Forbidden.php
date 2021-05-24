<?php

/**
 * Authorization failure - user is forbidden from viewing this page.

 * This failure roughly corresponds to a 403 Forbidden error - this error code
 * states that authentication will not make this response valid.
 * Therefore, the Unauthorized failure state is more appropriate for stating
 * that the authenticated user does not have permission to access a resource.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Failure;

use Schoolbox\Error\Mapper as ErrorMapper;

class Forbidden extends Base
{
    /**
     * Default error code - corresponds to message "you do not have permission..."
     *
     * @var int
     */
    protected $code = ErrorMapper::ERROR_PERM_PAGE_VIEW;
}
