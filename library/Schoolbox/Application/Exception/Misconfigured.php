<?php

/**
 * AccessDenied
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Exception;

class Misconfigured extends \Alaress\Authorizer\Failure\Base
{
    /**
     * Potentially 422 but that seems a bit uncommon for us
     *
     * @inheritdoc
     */
    protected $httpStatusCode = 403;
}
