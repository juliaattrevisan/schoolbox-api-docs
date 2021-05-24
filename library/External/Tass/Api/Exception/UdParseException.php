<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Exception;

use External\Tass\Api\ITassApiException;
use UnexpectedValueException;

/**
 * Thrown when unparseable/unrecognisable/unexpected UD data is encountered
 * @todo confirm that 'ud' means 'user-defined', and if so, update all usages to the explicit form
 */
class UdParseException extends UnexpectedValueException implements ITassApiException
{

}
