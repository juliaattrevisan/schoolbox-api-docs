<?php

namespace Schoolbox\Admin\SHAPS\Exception;

use GuzzleHttp\Exception\BadResponseException;

/**
 * Thrown when the client receives an unusable response from the Help server
 * For example: HTML instead of JSON, or something that isn't an array
 *
 * Class UnexpectedResponseException
 * @package Schoolbox\Admin\SHAPS\Exception
 */
class UnexpectedResponseException extends BadResponseException
{

}
