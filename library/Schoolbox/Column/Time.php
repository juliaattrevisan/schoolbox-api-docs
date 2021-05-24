<?php

/**
 * Time value column type, used to store a time of day.
 *
 * While both this column type and the Timespan type are stored as TIME columns,
 * their purpose is different - this represents a column for storing a time of
 * day, while the Timespan type represents a column for storing a span of time
 * in HH:MM:SS format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Time extends Datetime implements IColumnType
{
    protected $input_format  = 'H:i:s';
    protected $output_format = 'H:i:s';

    /**
     * There's no zero value for times.
     */
    protected const ZERO_VALUE = null;
}
