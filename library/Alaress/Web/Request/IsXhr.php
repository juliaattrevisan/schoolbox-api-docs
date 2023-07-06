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

/**
 * Is the given request XHR?
 *
 * @deprecated This depends on the `X-Requested-With` header, whose existence
 *             cannot be relied upon. Axios and HTTP fetch don't set it, and
 *             neither does Guzzle (because it's not performing XHRs at all).
 *             If you want JSON, then set the request's `Accept` header to
 *             `application/json`, and use `Alaress\Web\Request\IsJson` to
 *             detect that.
 */
class IsXhr extends Specification\Leaf
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
        return str_contains(strtolower($candidate->getHeaderLine('x-requested-with')), 'xmlhttprequest');
    }
}
