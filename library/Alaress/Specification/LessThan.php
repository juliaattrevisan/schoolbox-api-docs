<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

use UnexpectedValueException;

class LessThan extends ValueBoundSpecification
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        try {
            return $this->getExpectedValue() < $this->getActualValue($candidate);
        } catch (UnexpectedValueException $e) {
            return false;
        }
    }
}
