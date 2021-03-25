<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Exception;

use Throwable;

/**
 * This exception should never be thrown, it exists solely to assist the IDE with static analysis.
 *
 * However if (likely due to a logic bug), this exception is thrown, we do want it to bubble all the way out,
 * hence providing safety over a noop which would mask the underlying issue.
 *
 * To set up your PHPStorm:
 * 1. Visit  'Preferences' > 'Languages & Frameworks' > 'PHP' > 'Analysis'
 * 2. Add this exception to the 'Unchecked Exceptions' list.
 *
 * This exception should be used where some precondition guarantees an exception can never be thrown, eg:
 *
 * if($var === false){
 *     try {
 *         throwXIfFalse($var);
 *     } catch (X $e) {
 *         throw new UnreachableException($e);
 *     }
 * }
 *
 * In this case, we are sure that X can never be thrown, so we don't want callers of this code to have to handle it.
 *
 * @see https://blog.jetbrains.com/phpstorm/2017/11/bring-exceptions-under-control/
 * @see https://blog.jetbrains.com/phpstorm/2018/04/configurable-unchecked-exceptions/
 */
class UnreachableException extends \RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }
}
