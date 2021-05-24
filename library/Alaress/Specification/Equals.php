<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

use UnexpectedValueException;

class Equals extends ValueBoundSpecification
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        try {
            return $this->getExpectedValue() === $this->getActualValue($candidate);
        } catch (UnexpectedValueException $e) {
            return false;
        }
    }
}
