<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

/**
 * All components must be satisfied. Logical AND
 */
class ExclusiveOr extends Composite
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        $value = false;
        foreach($this->getComponents() as $component){
            $value = ($value xor $component->isSatisfiedBy($candidate));
        }
        return $value;
    }
}
