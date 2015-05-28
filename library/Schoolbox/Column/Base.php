<?php
/**
 * Most basic column type - doesn't perform any translation on hydration or
 * dehydration, besides ensuring that values are stored in valid UTF-8.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

class Base implements IColumnType
{

    public function hydrate($value, $mapper = null)
    {
        return $value;
    }


    public function dehydrate($value, $mapper = null)
    {
        // ensure data going into the database (or rabbit, or redis) is UTF-8 friendly
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}