<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Specification;

class Negation extends Base
{
    /** @var ISpecification */
    private $child;

    /**
     * @param ISpecification $child
     */
    public function __construct(ISpecification $child)
    {
        $this->child = $child;
    }

    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return !$this->child->isSatisfiedBy($candidate);
    }
}
