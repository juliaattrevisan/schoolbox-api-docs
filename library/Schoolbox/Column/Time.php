<?php
/**
 * Time value column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

class Time extends Datetime implements IColumnType {
	protected $input_format  = 'H:i:s';
	protected $output_format = 'H:i:s';
}