<?php

/**
 * Timestamp column type - the same thing as the datetime column type, but uses
 * Unix timestamps.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Timestamp extends Datetime implements IColumnType
{
    protected $input_format  = 'U';
    protected $output_format = 'U';

    /**
     * There's no zero value for timestamps.
     */
    protected const ZERO_VALUE = null;

    /**
     * Overridden to cast result to integer.
     *
     * @param mixed             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return int|null
     */
    public function dehydrate($value, $mapper = null)
    {
        $result = parent::dehydrate($value, $mapper);
        if (isset($result)) {
            $result = intval($result);
        }
        return $result;
    }
}
