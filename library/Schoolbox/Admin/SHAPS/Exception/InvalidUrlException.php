<?php

namespace Schoolbox\Admin\SHAPS\Exception;

/**
 * Thrown when the schoolbox_help_url in config doesn't return 200.
 * Depending on what is actually wrong with the URL string, a different exception may be thrown instead.
 *
 * Class InvalidUrlException
 * @package Schoolbox\Admin\SHAPS\Exception
 */
class InvalidUrlException extends RequestException {

}