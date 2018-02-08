<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
