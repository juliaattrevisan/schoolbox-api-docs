<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

/**
 * Any component may be satisfied. Logical OR
 */
class Disjunction extends Composite
{

    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        foreach ($this->getComponents() as $component) {
            if ($component->isSatisfiedBy($candidate)) {
                return true;
            }
        }
        return false;
    }
}
