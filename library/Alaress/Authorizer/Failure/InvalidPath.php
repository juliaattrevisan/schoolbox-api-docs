<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Failure;

use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * An auth version of the application's NotFound exception.
 * We cannot use NotFound as it does not extend Auth exception, hence will not be
 * caught in twig isRouteAuthorised calls and will bubble all the way up.
 */
class InvalidPath extends Base
{
    /**
     * Default error code - corresponds to message "404 Not Found"
     *
     * @var int
     */
    protected $code = ErrorMapper::ERROR_PAGE_NOT_FOUND;
}
