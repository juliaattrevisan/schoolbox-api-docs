<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

use Countable;
use TypeError;

/**
 * Spec pattern for validating that the result is countable and not empty.
 */
class NotEmpty extends Base
{
    /**
     * @param  array|Countable $candidate
     * @return bool
     * @throws TypeError if $candidate is neither an array or countable
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (is_array($candidate) || $candidate instanceof Countable) {
            return count($candidate) > 0;
        }
        throw new TypeError('$candidate must be either an array or a Countable object');
    }
}
