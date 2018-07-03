<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

interface ISpecification
{
    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool;


    /**
     * Returns which specifications are not yet satisfied by the candidate.
     *
     * This enables callers to programmatically determine what they need to do to satisfy a specification.
     *
     * Does not assume the $candidate fails to validate against this specification
     *
     * Returns NULL if this specification is satisfied by the $candidate
     *
     * @param mixed $candidate
     * @return null|ISpecification
     */
    public function remainderUnsatisfiedBy($candidate): ?ISpecification;
}
