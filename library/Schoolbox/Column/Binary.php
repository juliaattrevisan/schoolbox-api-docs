<?php

/**
 * Binary column type - doesn't perform any translation on hydration or
 * dehydration.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Binary implements IColumnType
{

    public function hydrate($value, $mapper = null)
    {
        return $value;
    }


    public function dehydrate($value, $mapper = null)
    {
        return $value;
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return true;
    }
}
