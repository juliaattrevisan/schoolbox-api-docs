<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

/**
 * All candidates will satisfy this spec
 *
 * Exists primarily for testing purposes
 */
class TrueLeaf extends Leaf
{

    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function remainderUnsatisfiedBy($candidate): ?ISpecification
    {
        return null;
    }
}
