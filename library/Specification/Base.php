<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Specification;

abstract class Base implements ISpecification
{
    /**
     * @inheritdoc
     */
    public function remainderUnsatisfiedBy($candidate): ?ISpecification
    {
        // Default implementation // TODO
        $satisified = $this->isSatisfiedBy($candidate);
        return $satisified ? null : clone $this;
    }
}
