<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

/**
 * Thrown if the Kami Api returns a response our connector does not understand
 */
class ApiException extends \UnexpectedValueException
{

}
