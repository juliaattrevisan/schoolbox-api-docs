<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

/**
 * Validates that a candidate value is an instance of some class
 */
class IsInstanceOf extends Leaf
{

    /** @var string */
    private $className;


    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }


    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return $candidate instanceof $this->className;
    }
}
