<?php
/**
 * Date column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

class Date extends Datetime implements IColumnType {
	protected $input_format  = 'Y-m-d';
	protected $output_format = 'Y-m-d';
}