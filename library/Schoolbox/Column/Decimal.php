<?php
/**
 * Decimal number column type. Converted to float in models.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

use Schoolbox\Mapper;

class Decimal implements IColumnType
{
    /**
     * @param string|null $value
     * @param null|Mapper $mapper
     * @return float|string
     */
    public function hydrate($value, $mapper = null)
    {
        return is_null($value) ? $value : floatval($value);
    }

    /**
     * @param null|float $value
     * @param null|Mapper $mapper
     * @return null|float
     */
    public function dehydrate($value, $mapper = null)
    {
        return $value === '' ? null : $value;
    }
}
