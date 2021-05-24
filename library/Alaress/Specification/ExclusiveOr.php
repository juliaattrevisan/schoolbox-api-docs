<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

/**
 * Either one or the other, but not both, component specs must be satisfied by the candidate.
 *
 * Logical XOR
 */
class ExclusiveOr extends Composite
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        $value = false;
        foreach ($this->getComponents() as $component) {
            $value = ($value xor $component->isSatisfiedBy($candidate));
        }
        return $value;
    }
}
