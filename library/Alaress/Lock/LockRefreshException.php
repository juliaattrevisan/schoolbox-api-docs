<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Lock;

/**
 * This is a specific alias to LockConflictedException and LockAcquiringException
 * These two exceptions are thrown when a lock couldn't be acquired for some reason
 * but to simplify treatment in our cli tools, we can use this one instead
 *
 * I don't necessarily want this to be silent captured by try/catching \RuntimeException
 * which is something that would typically happen in dev, so extend the base exception
 * instead, so it has a higher chance of being made visible in the cli
 */
class LockRefreshException extends \Exception
{

}
