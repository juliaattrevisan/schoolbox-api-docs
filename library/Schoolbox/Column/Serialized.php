<?php

/**
 * Serialized value column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Serialized implements IColumnType
{

    /**
     * @param string            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return mixed
     */
    public function hydrate($value, $mapper = null)
    {
        return unserialize($value);
    }

    /**
     * @param mixed             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return string
     */
    public function dehydrate($value, $mapper = null)
    {
        return serialize($value);
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        // cannot guarantee the hydrated value will always be scalar
        return false;
    }
}
