<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Web\Request;

use Alaress\Specification;
use Aura\Web\Request;

class IsXhr extends Specification\Leaf
{

    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param Request $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return $candidate->isXhr();
    }
}
