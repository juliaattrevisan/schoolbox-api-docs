<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use LogicException;

/**
 * Schema integrity exception.
 * Thrown if we try to create a participation for a user who is excluded from participating.
 */
class InvalidState extends LogicException
{

}
