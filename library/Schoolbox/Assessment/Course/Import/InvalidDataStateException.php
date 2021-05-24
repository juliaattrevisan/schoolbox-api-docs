<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import;

/**
 * Thrown when data fetched from DB appears to be invalid.
 *
 * There are some relationships we cannot enforce w/ FKs, eg that an assessment must be
 * referenced from one of the assessment sub-type tables.
 *
 * The invalid data may come about due to:
 * * logic errors
 * * direct DB manipulation
 */
class InvalidDataStateException extends \UnexpectedValueException
{

}
