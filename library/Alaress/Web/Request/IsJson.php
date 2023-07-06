<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Request;

use Alaress\Specification;
use Psr\Http\Message\ServerRequestInterface;
use TypeError;

class IsJson extends Specification\Leaf
{
    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param ServerRequestInterface $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof ServerRequestInterface) {
            throw new TypeError(
                sprintf(
                    '$candidate must be a %s object: received %s',
                    ServerRequestInterface::class,
                    get_class($candidate),
                )
            );
        }
        return str_contains($candidate->getHeaderLine('accept'), 'application/json');
    }
}
