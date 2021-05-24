<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Predicate;

use LogicException;

/**
 * Thrown if the predicate builder is unable to parse a column name into a table and field
 */
class InvalidColumnException extends LogicException
{

}
