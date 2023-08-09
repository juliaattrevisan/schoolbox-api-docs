<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Schoolbox\Search\Api\Cursor\RawResult;

/**
 * Base hydrator, with a simple implementation of `accepts` which requires a
 * `getBaseType` method be implemented.
 */
abstract class BaseHydrator implements IHydrator
{
    /**
     * This hydrator accepts any raw result whose:
     * * type matches `$this->getBaseType()`
     * * type starts with `$this->getBaseType() . '.'`
     */
    public function accepts(RawResult $match): bool
    {
        $baseType = $this->getBaseType();
        return $match->type === $baseType || str_starts_with($match->type, $baseType . '.');
    }

    /**
     * What is the base type this hydrator accepts?
     *
     * @return string
     */
    abstract protected function getBaseType(): string;
}
