<?php

/**
 * Integer column type.
 *
 * Accepts anything recognized by is_numeric, and converts to integer as best
 * it can.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

use Schoolbox\Mapper;

class Integer implements IColumnType
{
    /**
     * Convert this value to an integer.
     *
     * @param mixed $value
     * @return int|null
     */
    protected function convert($value)
    {
        if (is_numeric($value)) {
            // Does this have decimal places? If so convert to float then int
            if (preg_match('/\\./', $value)) {
                return intval(floatval($value));
            }
            return intval($value);
        }
        return null;
    }

    /**
     * @param mixed $value
     * @param Mapper $mapper
     * @return int|null
     */
    public function hydrate($value, $mapper = null)
    {
        return $this->convert($value);
    }

    /**
     * @param mixed $value
     * @param Mapper $mapper
     * @return int|null
     */
    public function dehydrate($value, $mapper = null)
    {
        return $this->convert($value);
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return true;
    }
}
