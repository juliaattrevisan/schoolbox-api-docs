<?php
/**
 * JSON-encoded value column type.
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

class JSON implements IColumnType
{

    /**
     * @param string            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return mixed
     */
    public function hydrate($value, $mapper = null)
    {
        return json_decode($value, true);
    }


    /**
     * @param mixed             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return string
     */
    public function dehydrate($value, $mapper = null)
    {
        return json_encode($value);
    }
}
