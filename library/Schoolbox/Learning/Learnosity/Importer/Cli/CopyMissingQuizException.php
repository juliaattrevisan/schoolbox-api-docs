<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use UnexpectedValueException;

/**
 * Exception class raised by Learnosity's copy process, when attempting to copy
 * a quiz which does not exist in Learnosity.
 *
 * This is prone to occurring a quiz created in a production Schoolbox instance
 * is copied on a staging instance after a prod to staging sync: the quiz data
 * existed on the production Learnosity instance, but not on the staging.
 */
class CopyMissingQuizException extends UnexpectedValueException
{
}
