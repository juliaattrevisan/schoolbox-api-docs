<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Web\Request;

use Alaress\Specification\ISpecification;
use Aura\Web\Request;


class IsXhr implements ISpecification
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


    /**
     * Returns which specifications are not yet satisfied by the candidate.
     *
     * This enables callers to programmatically determine what they need to do to satisfy a specification.
     *
     * Does not assume the $candidate fails to validate against this specification
     *
     * Returns NULL if this specification is satisfied by the $candidate
     *
     * @param Request $candidate
     * @return null|ISpecification
     */
    public function remainderUnsatisfiedBy($candidate): ?ISpecification
    {
        return $this->isSatisfiedBy($candidate) ? null : $this;
    }

}