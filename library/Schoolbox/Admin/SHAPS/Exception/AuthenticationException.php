<?php

namespace Schoolbox\Admin\SHAPS\Exception;

/**
 * Thrown when the auth digest calculated on the server side doesn't match the digest sent in the request.
 * This will happen if the school code or API key is wrong.
 *
 * Class AuthenticationException
 * @package Schoolbox\Admin\SHAPS\Exception
 */
class AuthenticationException extends RequestException {

}