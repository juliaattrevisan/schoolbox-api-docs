<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Specification;

class MoreThan extends ValueBoundSpecification
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        return $this->getValue() > $this->getPropertyValue($candidate);
    }
}
