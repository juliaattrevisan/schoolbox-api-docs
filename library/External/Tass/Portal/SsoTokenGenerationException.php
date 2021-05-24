<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Portal;

/**
 * Thrown when unable to generate an SSO token from the TASS SSO server
 */
class SsoTokenGenerationException extends \UnexpectedValueException
{

}
