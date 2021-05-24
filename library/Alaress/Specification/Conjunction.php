<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

/**
 * All components must be satisfied. Logical AND
 */
class Conjunction extends Composite
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        foreach ($this->getComponents() as $component) {
            if (!$component->isSatisfiedBy($candidate)) {
                return false;
            }
        }
        return true;
    }


    /**
     * @inheritdoc
     */
    public function remainderUnsatisfiedBy($candidate): ?ISpecification
    {
        // Special case: do not return the component Specifications that are met
        $unsatisfied = [];
        foreach ($this->getComponents() as $component) {
            if (!$component->isSatisfiedBy($candidate)) {
                $unsatisfied[] = $component;
            }
        }
        return \count($unsatisfied) ? new self($unsatisfied) : null;
    }
}
