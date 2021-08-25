<?php

/**
 * Decimal number column type. Converted to float in models.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

use Schoolbox\Mapper;

class Decimal implements IColumnType
{
    /**
     * @param string|null $value
     * @param null|Mapper $mapper
     * @return float|null
     */
    public function hydrate($value, $mapper = null)
    {
        return is_null($value) ? $value : floatval($value);
    }

    /**
     * @param float|null $value
     * @param null|Mapper $mapper
     * @return float|null
     */
    public function dehydrate($value, $mapper = null)
    {
        return $value === '' ? null : $value;
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return true;
    }
}
