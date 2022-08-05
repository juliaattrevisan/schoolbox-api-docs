<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Request;

use Alaress\Specification;
use Aura\Web\Request;
use TypeError;

class IsJson extends Specification\Leaf
{
    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param Request $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof Request) {
            throw new TypeError(
                sprintf(
                    '$candidate must be a "Aura\Web\Request" object: received %s',
                    get_class($candidate)
                )
            );
        }
        return strpos($candidate->headers->get('accept'), 'application/json') !== false;
    }
}
