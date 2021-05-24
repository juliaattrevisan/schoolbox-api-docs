<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

/**
 * Thrown if the Kami Api returns a response our connector does not understand
 */
class ApiException extends \UnexpectedValueException implements IKamiException
{

}
